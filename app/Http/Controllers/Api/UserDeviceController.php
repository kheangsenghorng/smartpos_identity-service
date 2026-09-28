<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use App\Services\SecurityEventService;

class UserDeviceController extends Controller
{
    /**
     * List registered devices for the authenticated user.
     */
    public function index()
    {
        return auth('api')
            ->user()
            ->devices()
            ->latest()
            ->get();
    }

    /**
     * Mark a device as trusted for the authenticated user.
     */
    public function trust(
        UserDevice $userDevice
    ) {
        abort_unless(
            $userDevice->user_id ===
            auth('api')->id(),
            403
        );

        $userDevice->update([
            'is_trusted' => true
        ]);

        SecurityEventService::record(
            eventType: SecurityEventService::DEVICE_TRUSTED,
            severity: SecurityEventService::SEVERITY_LOW,
            deviceUuid: $userDevice->device_uuid,
            description: "Device {$userDevice->device_name} marked as trusted."
        );

        return $userDevice;
    }

    /**
     * Block a device and revoke all active sessions associated with it.
     */
    public function block(
        UserDevice $userDevice
    ) {
        abort_unless(
            $userDevice->user_id ===
            auth('api')->id(),
            403
        );

        $userDevice->update([
            'is_blocked' => true
        ]);

        $userDevice->sessions()
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now()
            ]);

        SecurityEventService::record(
            eventType: SecurityEventService::DEVICE_BLOCKED,
            severity: SecurityEventService::SEVERITY_HIGH,
            deviceUuid: $userDevice->device_uuid,
            description: "Device {$userDevice->device_name} blocked and associated sessions revoked."
        );

        return $userDevice;
    }

    /**
     * Unblock a previously blocked device so it can authenticate again.
     */
    public function unblock(
        UserDevice $userDevice
    ) {
        abort_unless(
            $userDevice->user_id ===
            auth('api')->id(),
            403
        );

        $userDevice->update([
            'is_blocked' => false
        ]);

        return $userDevice;
    }

    /**
     * Untrust a device.
     */
    public function untrust(
        UserDevice $userDevice
    ) {
        abort_unless(
            $userDevice->user_id ===
            auth('api')->id(),
            403
        );

        $userDevice->update([
            'is_trusted' => false
        ]);

        return $userDevice;
    }
}