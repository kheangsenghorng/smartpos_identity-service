<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    /**
     * Assign a role to a user with tenant/outlet scoping and audit event logging.
     */
    public function store(
        Request $request,
        User $user
    ) {
        $data = $request->validate([
            'role_uuid' => [
                'required',
                'uuid',
                'exists:roles,uuid',
            ],
            'business_uuid' => [
                'nullable',
                'uuid',
            ],
            'outlet_uuid' => [
                'nullable',
                'uuid',
            ],
        ]);

        $role = Role::where(
            'uuid',
            $data['role_uuid']
        )->firstOrFail();

        $authUser = auth('api')->user();

        // Check delegation boundary if caller is not Super Admin or Root Admin
        if ($authUser) {
            $hasAuthority = $authUser->roles()->get()->some(fn (Role $r) => $r->canAssign($role));
            if (! $hasAuthority && ! $authUser->is_owner) {
                SecurityEvent::log(
                    eventType: 'UNAUTHORIZED_ROLE_DELEGATION_ATTEMPT',
                    userUuid: $authUser->uuid,
                    businessUuid: $data['business_uuid'] ?? null,
                    metadata: [
                        'target_user_uuid' => $user->uuid,
                        'attempted_role' => $role->code,
                    ],
                    severity: 'critical'
                );

                return response()->json([
                    'message' => 'You are not authorized to assign this role.',
                ], 403);
            }
        }

        $user->roles()
            ->syncWithoutDetaching([
                $role->id => [
                    'business_uuid' => $data['business_uuid'] ?? null,
                    'outlet_uuid' => $data['outlet_uuid'] ?? null,
                    'assigned_by_uuid' => $authUser?->uuid,
                    'is_active' => true,
                ],
            ]);

        $user->clearRbacCache();

        // Audit Log
        SecurityEvent::log(
            eventType: 'ROLE_ASSIGNED',
            userUuid: $user->uuid,
            businessUuid: $data['business_uuid'] ?? null,
            metadata: [
                'role_code' => $role->code,
                'assigned_by' => $authUser?->uuid,
                'outlet_uuid' => $data['outlet_uuid'] ?? null,
            ],
            severity: 'info'
        );

        return $user->load('roles');
    }

    /**
     * Remove a role from a user.
     */
    public function destroy(
        User $user,
        Role $role
    ) {
        $authUser = auth('api')->user();

        $user->roles()->detach($role->id);
        $user->clearRbacCache();

        // Audit Log
        SecurityEvent::log(
            eventType: 'ROLE_REMOVED',
            userUuid: $user->uuid,
            businessUuid: $role->business_uuid,
            metadata: [
                'role_code' => $role->code,
                'removed_by' => $authUser?->uuid,
            ],
            severity: 'warning'
        );

        // Revoke active sessions if an administrative or critical role is removed
        $criticalRoles = ['owner', 'admin', 'super_admin', 'inventory_admin', 'finance_admin', 'pos_admin', 'hr_admin'];
        if (in_array(strtolower($role->code), $criticalRoles, true)) {
            UserSession::where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                ]);
        }

        return response()->json([
            'message' => 'Role removed.'
        ]);
    }
}