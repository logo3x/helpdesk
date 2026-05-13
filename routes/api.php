<?php

use App\Http\Controllers\Api\DeployController;
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

// ── Deploy webhook ───────────────────────────────────────────
// Sin auth:sanctum (no hay sesión web ni token Sanctum involucrado)
// — la autenticación la hace el controller con un token shared en
// .env (DEPLOY_TOKEN) usando hash_equals para evitar timing attacks.
// Throttle conservador: 5 reqs/min — un deploy razonable corre cada
// varios minutos, esta cota previene fuerza-bruta sobre el token.
Route::middleware('throttle:5,1')
    ->prefix('deploy')
    ->group(function () {
        Route::post('/', [DeployController::class, 'trigger']);
        Route::get('log', [DeployController::class, 'log']);
    });
