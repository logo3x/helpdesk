<?php

namespace App\Filament\Soporte\Widgets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Soporte\Resources\KbArticles\KbArticleResource;
use App\Filament\Soporte\Resources\Tickets\TicketResource;
use App\Models\KbArticle;
use App\Models\Ticket;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stats del panel /soporte. El scope depende del rol del usuario:
 *
 *   - super_admin / admin → todo el sistema (sin filtro).
 *   - supervisor_soporte  → su departamento entero.
 *   - agente / técnico    → solo tickets de su depto asignados a él
 *                            o sin asignar (igual que el listado).
 *
 * Esto evita que un supervisor de RRHH vea el conteo de TI o que un
 * agente vea tickets que no le corresponden.
 */
class TicketStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $user = auth()->user();
        $isSupervisor = $user?->hasRole('supervisor_soporte') ?? false;
        $isAdmin = $user?->hasAnyRole(['super_admin', 'admin']) ?? false;

        $open = $this->scopedTicketQuery()->open()->count();
        $newOrReopened = $this->scopedTicketQuery()
            ->whereIn('status', [TicketStatus::Nuevo, TicketStatus::Reabierto])
            ->count();
        $critical = $this->scopedTicketQuery()
            ->open()
            ->whereIn('priority', [TicketPriority::Alta, TicketPriority::Critica])
            ->count();
        $assignedToMe = Ticket::query()
            ->open()
            ->where('assigned_to_id', $user?->id)
            ->count();

        // URLs base para los stats clickeables. tableFilters[name][...]
        // sigue el formato que Filament v5 espera para deep-linking.
        $ticketsBase = TicketResource::getUrl('index');
        $openStatuses = ['nuevo', 'asignado', 'en_progreso', 'pendiente_cliente', 'reabierto'];

        $stats = [
            Stat::make('Tickets abiertos', $open)
                ->description($isAdmin ? 'Todo el sistema' : ($isSupervisor ? 'En tu departamento' : 'En tu cola'))
                ->descriptionIcon('heroicon-m-inbox')
                ->color('primary')
                ->url($ticketsBase.'?'.http_build_query([
                    'tableFilters' => ['status' => ['values' => $openStatuses]],
                ])),

            Stat::make('Sin asignar / reabiertos', $newOrReopened)
                ->description('Requieren triage')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color($newOrReopened > 0 ? 'warning' : 'gray')
                ->url($ticketsBase.'?'.http_build_query([
                    'tableFilters' => ['status' => ['values' => ['nuevo', 'reabierto']]],
                ])),

            Stat::make('Prioridad alta/crítica', $critical)
                ->description('Abiertos con SLA crítico')
                ->descriptionIcon('heroicon-m-fire')
                ->color($critical > 0 ? 'danger' : 'success')
                ->url($ticketsBase.'?'.http_build_query([
                    'tableFilters' => [
                        'status' => ['values' => $openStatuses],
                        'priority' => ['values' => ['alta', 'critica']],
                    ],
                ])),

            Stat::make('Asignados a mí', $assignedToMe)
                ->description('Abiertos en mi cola')
                ->descriptionIcon('heroicon-m-user')
                ->color('info')
                ->url($ticketsBase.'?'.http_build_query([
                    'tableFilters' => [
                        'status' => ['values' => $openStatuses],
                        'assigned_to_id' => ['value' => $user?->id],
                    ],
                ])),
        ];

        // Stats adicionales solo para supervisor: tamaño del equipo y
        // KB pendientes de aprobar. Para super_admin/admin no aplica
        // porque no tienen un equipo concreto a su cargo.
        if ($isSupervisor && $user?->department_id) {
            $teamSize = User::query()
                ->where('department_id', $user->department_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['agente_soporte', 'tecnico_campo']))
                ->count();

            $kbPending = KbArticle::query()
                ->where('department_id', $user->department_id)
                ->where('status', 'draft')
                ->whereNotNull('pending_review_at')
                ->count();

            $stats[] = Stat::make('Mi equipo', $teamSize)
                ->description('Agentes/técnicos en tu depto')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('gray');

            $stats[] = Stat::make('KB por aprobar', $kbPending)
                ->description('Borradores pendientes de revisión')
                ->descriptionIcon('heroicon-m-document-magnifying-glass')
                ->color($kbPending > 0 ? 'warning' : 'gray')
                ->url(KbArticleResource::getUrl('index').'?'.http_build_query([
                    'tableFilters' => ['pending_review' => ['isActive' => true]],
                ]));
        }

        return $stats;
    }

    /**
     * Query base de tickets con el scope del rol aplicado. Sirve para
     * que todas las stats que muestran "totales del usuario" usen la
     * misma semántica que la tabla del Resource.
     *
     * @return Builder<Ticket>
     */
    protected function scopedTicketQuery(): Builder
    {
        /** @var Builder<Ticket> $query */
        $query = Ticket::query();
        $user = auth()->user();

        if (! $user || $user->hasAnyRole(['super_admin', 'admin'])) {
            return $query;
        }

        if ($user->department_id) {
            $query->where('department_id', $user->department_id);
        } else {
            $query->whereRaw('0 = 1');
        }

        if (! $user->hasRole('supervisor_soporte')) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('assigned_to_id', $user->id)
                    ->orWhereNull('assigned_to_id');
            });
        }

        return $query;
    }
}
