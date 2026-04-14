<?php

use App\Http\Controllers\Api\InventoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ── Inventory endpoints ──────────────────────────────────────
Route::middleware('auth:sanctum')->prefix('inventory')->group(function () {
    Route::post('web-scan', [InventoryController::class, 'webScan']);
    Route::post('agent-scan', [InventoryController::class, 'agentScan']);
});
