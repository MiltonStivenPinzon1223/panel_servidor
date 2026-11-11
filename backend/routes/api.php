<?php

use App\Http\Controllers\Api\V1\PanelController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/ping', [PanelController::class, 'ping']);
    Route::post('/auth/login', [PanelController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [PanelController::class, 'logout']);
        Route::get('/auth/me', [PanelController::class, 'me']);
        Route::get('/server/status', [PanelController::class, 'status']);
        Route::get('/apps', [PanelController::class, 'apps']);
        Route::post('/apps', [PanelController::class, 'storeApp']);
        Route::get('/apps/{app}', [PanelController::class, 'showApp']);
        Route::patch('/apps/{app}', [PanelController::class, 'updateApp']);
        Route::delete('/apps/{app}', [PanelController::class, 'archiveApp']);
        Route::get('/technologies', [PanelController::class, 'technologies']);
        Route::post('/technologies', [PanelController::class, 'storeTechnology']);
        Route::get('/alerts', [PanelController::class, 'alerts']);
        Route::get('/alert-rules', [PanelController::class, 'alertRules']);
        Route::patch('/alert-rules/{id}', [PanelController::class, 'updateAlertRule']);
        Route::post('/devices', [PanelController::class, 'registerDevice']);
        Route::delete('/devices/{id}', [PanelController::class, 'deleteDevice']);
        Route::post('/devices/{id}/test', [PanelController::class, 'testDevice']);
    });
});
