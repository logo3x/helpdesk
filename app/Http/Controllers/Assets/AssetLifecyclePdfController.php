<?php

namespace App\Http\Controllers\Assets;

use App\Filament\Resources\Assets\Pages\AssetLifecycle;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Response;

class AssetLifecyclePdfController extends Controller
{
    public function __invoke(Asset $asset): Response
    {
        $this->authorize('viewAny', Asset::class);

        $asset->loadMissing([
            'user',
            'department',
            'project',
            'maintenanceResponsible',
            'handovers.receivedBy',
            'handovers.deliveredBy',
            'histories.user',
            'scans',
            'software',
        ]);

        $page = new AssetLifecycle;
        $page->record = $asset;
        $events = $page->getTimeline();

        $html = view('filament.resources.assets.lifecycle-pdf', [
            'record' => $asset,
            'events' => $events,
        ])->render();

        return response($html)->header('Content-Type', 'text/html');
    }
}
