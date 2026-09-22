<?php

use App\Http\Controllers\Api\SecurityEventController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Security Events API Routes
|--------------------------------------------------------------------------
*/

Route::get(
    '/security-events',
    [SecurityEventController::class, 'index']
)->middleware(
    'permission:security_events.view'
);

Route::get(
    '/security-events/{securityEvent}',
    [SecurityEventController::class, 'show']
)->middleware(
    'permission:security_events.view'
);
