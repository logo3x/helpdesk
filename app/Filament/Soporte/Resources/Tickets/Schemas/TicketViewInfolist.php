<?php

namespace App\Filament\Soporte\Resources\Tickets\Schemas;

use App\Models\Ticket;
use App\Services\SlaService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

/**
 * Vista del ticket en UNA columna con secciones colapsables.
 *
 * Diseño: orden de lectura vertical para minimizar el scroll horizontal
 * y permitir que el agente pliegue lo que no le interesa.
 *
 *   1. Resumen del caso          (abierto)   — escaneo rápido.
 *   2. Descripción del problema  (abierto)   — el reporte original.
 *   3. Adjuntos                  (abierto)   — solo si hay.
 *   4. SLA y tiempos             (colapsado) — métricas con tooltip ?
 *   5. Clasificación técnica     (colapsado) — impacto/urgencia/estado.
 *
 * Todos los entries del bloque SLA llevan `helperText` con la definición
 * de la métrica para que el agente o supervisor sepa exactamente qué
 * está leyendo.
 */
class TicketViewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Resumen del caso')
                    ->icon('heroicon-o-identification')
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('requester.name')
                                    ->label('Solicitante')
                                    ->icon('heroicon-o-user')
                                    ->placeholder('—')
                                    ->helperText(fn (Ticket $record) => $record->requester?->department?->name),

                                TextEntry::make('assignee.name')
                                    ->label('Asignado a')
                                    ->icon('heroicon-o-user-circle')
                                    ->placeholder('Sin asignar')
                                    ->color(fn (Ticket $record) => $record->assignee ? 'success' : 'warning'),

                                TextEntry::make('category.name')
                                    ->label('Categoría')
                                    ->icon('heroicon-o-tag')
                                    ->placeholder('Sin categoría')
                                    ->helperText(fn (Ticket $record) => $record->department?->name),

                                TextEntry::make('priority')
                                    ->label('Prioridad')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state?->getLabel())
                                    ->color(fn ($state) => match ($state?->value) {
                                        'planificada' => 'gray',
                                        'baja' => 'info',
                                        'media' => 'warning',
                                        'alta', 'critica' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                    ]),

                Section::make('Descripción del problema')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->markdown()
                            ->columnSpanFull(),
                    ]),

                Section::make('Adjuntos')
                    ->icon('heroicon-o-paper-clip')
                    ->collapsible()
                    ->columnSpanFull()
                    ->visible(fn (Ticket $record) => $record->getMedia('attachments')->count() > 0)
                    ->schema([
                        TextEntry::make('attachments_list')
                            ->hiddenLabel()
                            ->state(function (Ticket $record): string {
                                return $record->getMedia('attachments')
                                    ->map(fn ($m) => sprintf(
                                        '<a href="%s" target="_blank" class="inline-flex items-center gap-1 rounded border border-zinc-200 bg-white px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 me-2 mb-1">📎 %s <span class="text-zinc-400">(%s)</span></a>',
                                        e($m->getUrl()),
                                        e($m->file_name),
                                        Number::fileSize($m->size),
                                    ))
                                    ->implode('');
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('SLA y tiempos')
                    ->icon('heroicon-o-clock')
                    ->description('Pasá el mouse sobre el ícono ⓘ de cada métrica para ver la definición.')
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Creado')
                                    ->dateTime('d M Y H:i')
                                    ->since()
                                    ->helperText('Fecha y hora en que el solicitante creó el ticket. Origen del reloj de SLA.'),

                                TextEntry::make('first_responded_at')
                                    ->label('Primera respuesta')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('Pendiente')
                                    ->color(fn (Ticket $record) => $record->first_responded_at ? 'success' : 'warning')
                                    ->helperText('Momento en que un agente respondió o tomó el ticket por primera vez. Detiene el reloj de "primera respuesta" del SLA.'),

                                TextEntry::make('first_response_due_at')
                                    ->label('Vence primera respuesta')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('—')
                                    ->color(fn (Ticket $record) => $record->first_response_breached ? 'danger' : 'gray')
                                    ->helperText('Objetivo de SLA para la primera respuesta. Calculado al crear el ticket usando minutos hábiles (L-V 08:00-18:00).'),

                                TextEntry::make('resolution_due_at')
                                    ->label('Vence resolución')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('—')
                                    ->color(fn (Ticket $record) => $record->resolution_breached ? 'danger' : 'gray')
                                    ->helperText('Objetivo de SLA para resolver. Si resolved_at lo supera, el ticket entra en "fuera de SLA".'),

                                TextEntry::make('resolved_at')
                                    ->label('Resuelto')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('—')
                                    ->helperText('Momento en que el agente marcó el ticket como resuelto. A partir de aquí cuenta el tiempo Resuelto→Cerrado.'),

                                TextEntry::make('closed_at')
                                    ->label('Cerrado')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('—')
                                    ->helperText('Confirmación del solicitante o auto-cierre del job programado tras X días sin respuesta.'),

                                TextEntry::make('paused_minutes')
                                    ->label('Tiempo pausado (min)')
                                    ->state(fn (Ticket $record) => self::formatPausedMinutes($record))
                                    ->placeholder('0 min')
                                    ->helperText('Minutos hábiles acumulados en pendiente_cliente. El SLA se detiene en ese estado porque la demora no es del agente.'),

                                TextEntry::make('solution_time')
                                    ->label('Tiempo de solución')
                                    ->state(fn (Ticket $record) => self::computeSolutionTime($record))
                                    ->placeholder('—')
                                    ->helperText('Trabajo efectivo del agente: minutos hábiles entre creación y resolución, descontando el tiempo pausado.'),

                                TextEntry::make('resolved_to_closed')
                                    ->label('Resuelto → Cerrado')
                                    ->state(fn (Ticket $record) => self::computeResolvedToClosed($record))
                                    ->placeholder('—')
                                    ->helperText('Horas calendario que el cliente tardó en confirmar la solución (o que esperamos hasta auto-cerrar).'),

                                IconEntry::make('first_response_breached')
                                    ->label('¿SLA primera respuesta?')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-exclamation-triangle')
                                    ->trueColor('danger')
                                    ->falseIcon('heroicon-o-check-circle')
                                    ->falseColor('success')
                                    ->helperText('Indicador rojo = SLA de primera respuesta vencido. Verde = cumplido o aún dentro de plazo.'),

                                IconEntry::make('resolution_breached')
                                    ->label('¿SLA resolución?')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-exclamation-triangle')
                                    ->trueColor('danger')
                                    ->falseIcon('heroicon-o-check-circle')
                                    ->falseColor('success')
                                    ->helperText('Indicador rojo = SLA de resolución vencido. Verde = cumplido o aún dentro de plazo.'),
                            ]),
                    ]),

                Section::make('Clasificación técnica')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 3])
                            ->schema([
                                TextEntry::make('impact')
                                    ->label('Impacto')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state?->getLabel())
                                    ->color('gray')
                                    ->helperText('Alcance del problema: cuántos usuarios o servicios afecta.'),

                                TextEntry::make('urgency')
                                    ->label('Urgencia')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state?->getLabel())
                                    ->color('gray')
                                    ->helperText('Qué tan rápido debe resolverse desde la perspectiva del solicitante.'),

                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state?->getLabel())
                                    ->color(fn ($state) => match ($state?->value) {
                                        'nuevo' => 'info',
                                        'asignado' => 'primary',
                                        'en_progreso' => 'warning',
                                        'pendiente_cliente' => 'gray',
                                        'resuelto' => 'success',
                                        'cerrado' => 'gray',
                                        'reabierto' => 'danger',
                                        default => 'gray',
                                    })
                                    ->helperText('Etapa actual del ticket. En pendiente_cliente el reloj de SLA queda pausado.'),
                            ]),
                    ]),
            ]);
    }

    protected static function formatPausedMinutes(Ticket $ticket): string
    {
        $minutes = (int) $ticket->paused_minutes;

        if ($ticket->paused_at) {
            $live = app(SlaService::class)->businessMinutesBetween($ticket->paused_at, now());
            $minutes += $live;
        }

        if ($minutes === 0) {
            return '0 min';
        }

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
    }

    protected static function computeSolutionTime(Ticket $ticket): ?string
    {
        if ($ticket->resolved_at === null) {
            return null;
        }

        $total = app(SlaService::class)
            ->businessMinutesBetween($ticket->created_at, $ticket->resolved_at);

        $effective = max(0, $total - (int) $ticket->paused_minutes);

        if ($effective === 0) {
            return '0 min';
        }

        if ($effective < 60) {
            return $effective.' min';
        }

        $h = intdiv($effective, 60);
        $m = $effective % 60;

        return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
    }

    protected static function computeResolvedToClosed(Ticket $ticket): ?string
    {
        if ($ticket->resolved_at === null || $ticket->closed_at === null) {
            return null;
        }

        $hours = $ticket->resolved_at->diffInHours($ticket->closed_at);

        return number_format($hours, 1).' h';
    }
}
