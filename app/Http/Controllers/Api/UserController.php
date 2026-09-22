<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * List users with loaded roles, with optional search, role filter, status filter, and pagination.
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->with('roles');

        // Filter by role code or role UUID (e.g. role=owner)
        if ($request->filled('role')) {
            $roleParam = (string) $request->input('role');
            $query->whereHas('roles', function ($q) use ($roleParam) {
                $q->where('roles.code', $roleParam)
                    ->orWhere('roles.uuid', $roleParam);
            });
        } elseif ($request->filled('role_code')) {
            $roleCode = (string) $request->input('role_code');
            $query->whereHas('roles', function ($q) use ($roleCode) {
                $q->where('roles.code', $roleCode);
            });
        }

        // Filter by status (e.g. status=active)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by name, email, phone, or username
        if ($request->filled('search')) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search)
                    ->orWhere('username', 'like', $search);
            });
        }

        $query->latest();

        // Support returning all results or custom per_page limit
        if ($request->boolean('all') || $request->input('paginate') === 'false') {
            return response()->json([
                'data' => $query->get(),
            ]);
        }

        $perPage = (int) $request->input('per_page', 20);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 20;
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new user account with optional auto-assigned role template.
     */
    public function store(
        Request $request,
        \App\Services\RoleProvisionService $provisioner
    ) {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'username' => [
                'nullable',
                'string',
                'max:100',
                'unique:users,username',
            ],

            'email' => [
                'nullable',
                'email',
                'max:150',
                'unique:users,email',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
                'unique:users,phone',
            ],

            'password' => [
                'nullable',
                Password::min(8),
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'active',
                    'inactive',
                    'blocked',
                ]),
            ],

            'role_code' => [
                'sometimes',
                'string',
                'max:100',
            ],

            'role_uuid' => [
                'sometimes',
                'uuid',
                'exists:roles,uuid',
            ],

            'business_uuid' => [
                'sometimes',
                'uuid',
            ],
        ]);

        $userData = collect($data)->only([
            'name',
            'username',
            'email',
            'phone',
            'password',
            'status',
        ])->all();

        $user = User::create($userData);

        // Auto-assign role template if specified
        $roleToAttach = null;

        if (! empty($data['role_uuid'])) {
            $roleToAttach = \App\Models\Role::where('uuid', $data['role_uuid'])->first();
        } elseif (! empty($data['role_code'])) {
            $roleCode = strtolower(trim($data['role_code']));
            $businessUuid = $data['business_uuid'] ?? null;

            if ($businessUuid) {
                $roleToAttach = \App\Models\Role::where('business_uuid', $businessUuid)
                    ->where('code', $roleCode)
                    ->first();

                // If role does not exist for this business yet, auto-provision default templates
                if (! $roleToAttach) {
                    $provisioned = $provisioner->provisionForBusiness($businessUuid);
                    $roleToAttach = $provisioned->firstWhere('code', $roleCode);
                }
            }

            // Fallback to global/system role template
            if (! $roleToAttach) {
                $roleToAttach = \App\Models\Role::where('code', $roleCode)->first();
            }
        }

        if ($roleToAttach) {
            $user->roles()->syncWithoutDetaching([$roleToAttach->id]);
            $user->clearRbacCache();
        }

        return response()->json([
            'message' => 'User created.',
            'data' => $user->load('roles.permissions'),
        ], 201);
    }

    /**
     * Get user details including roles, permissions, and registered devices.
     */
    public function show(User $user)
    {
        return $user->load([
            'roles.permissions',
            'devices',
        ]);
    }

    /**
     * Update user details.
     */
    public function update(
        Request $request,
        User $user
    ) {
        $data = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:150'
            ],

            'username' => [
                'sometimes',
                'nullable',
                Rule::unique('users', 'username')
                    ->ignore($user->id),
            ],

            'email' => [
                'sometimes',
                'nullable',
                'email',
                Rule::unique('users', 'email')
                    ->ignore($user->id),
            ],

            'phone' => [
                'sometimes',
                'nullable',
                Rule::unique('users', 'phone')
                    ->ignore($user->id),
            ],

            'password' => [
                'sometimes',
                'nullable',
                Password::min(8),
            ],

            'status' => [
                'sometimes',
                Rule::in([
                    'active',
                    'inactive',
                    'blocked'
                ]),
            ],
        ]);

        $user->update($data);
        $user->clearRbacCache();

        if (! empty($data['password']) || (isset($data['status']) && in_array($data['status'], ['blocked', 'inactive']))) {
            UserSession::where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                ]);
        }

        return response()->json([
            'message' => 'User updated.',
            'data' => $user->fresh(),
        ]);
    }

    /**
     * Delete a user account.
     */
    public function destroy(User $user)
    {
        UserSession::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);

        $user->clearRbacCache();
        $user->delete();

        return response()->json([
            'message' => 'User deleted.'
        ]);
    }
}