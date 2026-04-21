<?php

use App\Http\Controllers\Api\InventoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ── Inventory endpoints ──────────────────────────────────────
// auth:sanctum + throttle estricto + el controller valida
// tokenCan('inventory:scan') para que un token filtrado de un
// usuario final NO pueda POSTear scans arbitrarios.
Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('inventory')
    ->group(function () {
        Route::post('web-scan', [InventoryController::class, 'webScan']);
        Route::post('agent-scan', [InventoryController::class, 'agentScan']);
    });
