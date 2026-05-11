<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hoja de vida (lifecycle) de un activo del inventario.
 *
 * Página de detalle "solo lectura" que agrega en un timeline unificado
 * todos los eventos relevantes del activo:
 *   - Creación del activo.
 *   - Scans (web/agent) con sus huellas de IP/UA.
 *   - Actas de entrega (handovers) con custodios y condición.
 *   - Cambios manuales en AssetHistory.
 *   - Instalaciones de software relevantes.
 *
 * Filament 5: declarada en AssetResource::getPages() con la ruta
 * `/{record}/lifecycle`. Recibe `$record` por path-binding.
 */
class AssetLifecycle extends Page
{
    protected static string $resource = AssetResource::class;

    protected string $view = 'filament.resources.assets.pages.lifecycle';

    public Asset $record;

    public function mount(int|string $record): void
    {
        $this->record = AssetResource::resolveRecordRouteBinding($record)
            ?? throw new NotFoundHttpException;

        $this->record->loadMissing([
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
    }

    public function getTitle(): string|Htmlable
    {
        return 'Hoja de vida — '.($this->record->asset_tag ?: $this->record->hostname ?: 'Activo #'.$this->record->id);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $parts = array_filter([
            $this->record->manufacturer,
            $this->record->model,
            $this->record->serial_number ? 'S/N '.$this->record->serial_number : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToEdit')
                ->label('← Volver al activo')
                ->color('gray')
                ->url(AssetResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    /**
     * Timeline unificado, ordenado por fecha descendente.
     *
     * @return array<int, array{
     *     date: CarbonInterface,
     *     type: string,
     *     icon: string,
     *     color: string,
     *     title: string,
     *     description: ?string,
     *     meta: array<string, ?string>
     * }>
     */
    public function getTimeline(): array
    {
        $events = [];

        // 1. Creación del activo (siempre el evento más antiguo).
        $events[] = [
            'date' => $this->record->created_at,
            'type' => 'created',
            'icon' => 'heroicon-o-sparkles',
            'color' => 'primary',
            'title' => 'Activo registrado',
            'description' => 'Se creó el registro del activo en el inventario.',
            'meta' => [
                'TAG' => $this->record->asset_tag,
                'Hostname' => $this->record->hostname,
                'Serial' => $this->record->serial_number,
            ],
        ];

        // 2. Actas de entrega.
        foreach ($this->record->handovers as $handover) {
            $events[] = [
                'date' => $handover->delivered_at,
                'type' => 'handover',
                'icon' => 'heroicon-o-document-text',
                'color' => 'warning',
                'title' => "Acta de entrega #{$handover->acta_number}",
                'description' => 'Recibe: '.($handover->receivedBy?->name ?? '—')
                    .' · Condición: '.ucfirst((string) $handover->condition_at_delivery),
                'meta' => [
                    'Entrega' => $handover->deliveredBy?->name,
                    'Referencia' => $handover->reference,
                    'Observaciones' => $handover->observations,
                ],
            ];
        }

        // 3. Cambios manuales registrados en AssetHistory.
        foreach ($this->record->histories as $history) {
            $events[] = [
                'date' => $history->created_at,
                'type' => 'history',
                'icon' => 'heroicon-o-pencil-square',
                'color' => 'gray',
                'title' => $this->labelForAction($history->action, $history->field),
                'description' => $history->notes,
                'meta' => array_filter([
                    'Usuario' => $history->user?->name,
                    'Campo' => $history->field,
                    'Antes' => $history->old_value,
                    'Después' => $history->new_value,
                ]),
            ];
        }

        // 4. Scans (capamos a los últimos 50 para no saturar el timeline
        // — un PC escanea cada semana, en 1 año son 50+).
        foreach ($this->record->scans->take(50) as $scan) {
            $events[] = [
                'date' => $scan->created_at,
                'type' => 'scan',
                'icon' => 'heroicon-o-signal',
                'color' => 'info',
                'title' => 'Scan recibido ('.($scan->source ?: 'desconocido').')',
                'description' => null,
                'meta' => array_filter([
                    'IP' => $scan->ip_address,
                    'User-Agent' => $scan->user_agent ? mb_strimwidth((string) $scan->user_agent, 0, 80, '…') : null,
                ]),
            ];
        }

        // Ordenamos por fecha descendente (más reciente primero).
        usort($events, fn ($a, $b) => $b['date']->getTimestamp() <=> $a['date']->getTimestamp());

        return $events;
    }

    protected function labelForAction(?string $action, ?string $field): string
    {
        return match ($action) {
            'created' => 'Activo creado',
            'updated' => $field ? "Campo «{$field}» actualizado" : 'Activo actualizado',
            'assigned' => 'Custodio asignado',
            'unassigned' => 'Custodio retirado',
            'maintenance' => 'Mantenimiento registrado',
            'retired' => 'Activo dado de baja',
            default => $action ? ucfirst($action) : 'Cambio registrado',
        };
    }
}
