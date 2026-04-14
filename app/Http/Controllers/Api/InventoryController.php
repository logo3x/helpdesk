<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentScanRequest;
use App\Http\Requests\WebScanRequest;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventory,
    ) {}

    /**
     * POST /api/inventory/web-scan
     *
     * Receives browser-collected inventory data from the portal JS collector.
     * Authenticated via Sanctum (session cookie from the logged-in user).
     */
    public function webScan(WebScanRequest $request): JsonResponse
    {
        $asset = $this->inventory->processWebScan(
            data: $request->validated(),
            user: $request->user(),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'message' => 'Web scan processed.',
            'asset_id' => $asset->id,
            'hostname' => $asset->hostname,
        ]);
    }

    /**
     * POST /api/inventory/agent-scan
     *
     * Receives full inventory from the PowerShell agent.
     * Authenticated via Sanctum token (Bearer token).
     */
    public function agentScan(AgentScanRequest $request): JsonResponse
    {
        $asset = $this->inventory->processAgentScan(
            data: $request->validated(),
            ip: $request->ip(),
        );

        return response()->json([
            'message' => 'Agent scan processed.',
            'asset_id' => $asset->id,
            'hostname' => $asset->hostname,
        ]);
    }
}
