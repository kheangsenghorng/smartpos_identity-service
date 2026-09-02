<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SmartPOS Identity Service API
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Health check
    Route::get('/identity/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'smartpos-identity-service',
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Process Report Documentation (Protected)
    Route::middleware('auth:api')->get('/IDENTITY_SERVICE_PROCESS_REPORT.md', function () {
        $path = base_path('IDENTITY_SERVICE_PROCESS_REPORT.md');
        if (!file_exists($path)) {
            $path = base_path('SYSTEM_ARCHITECTURE.md');
        }
        if (!file_exists($path)) {
            abort(404, 'Process report file not found.');
        }
        return response()->file($path, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="IDENTITY_SERVICE_PROCESS_REPORT.md"',
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    require __DIR__.'/api/auth.php';


    /*
    |--------------------------------------------------------------------------
    | Protected Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:api', 'session.active'])->group(function () {

        require __DIR__.'/api/users.php';

        require __DIR__.'/api/roles.php';

        require __DIR__.'/api/permissions.php';

        require __DIR__.'/api/user_roles.php';

        require __DIR__.'/api/pos_pins.php';

        require __DIR__.'/api/devices.php';

        require __DIR__.'/api/sessions.php';

        require __DIR__.'/api/login_attempts.php';
    });

});