<?php

use App\Http\Controllers\Api\UserDeviceController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Devices
|--------------------------------------------------------------------------
*/

Route::get(
    '/devices',
    [UserDeviceController::class, 'index']
)->middleware(
    'permission:devices.view'
);


Route::patch(
    '/devices/{userDevice}/trust',
    [UserDeviceController::class, 'trust']
)->middleware(
    'permission:devices.trust'
);


Route::patch(
    '/devices/{userDevice}/block',
    [UserDeviceController::class, 'block']
)->middleware(
    'permission:devices.block'
);


Route::patch(
    '/devices/{userDevice}/unblock',
    [UserDeviceController::class, 'unblock']
)->middleware(
    'permission:devices.block'
);


Route::patch(
    '/devices/{userDevice}/untrust',
    [UserDeviceController::class, 'untrust']
)->middleware(
    'permission:devices.trust'
);