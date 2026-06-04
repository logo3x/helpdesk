<?php

namespace App\Filament\Soporte\Resources\Tickets\Schemas;

use App\Models\Ticket;
use App\Services\SlaService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
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
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(self::labelWithTip('Creado', 'Fecha y hora en que el solicitante creó el ticket. Origen del reloj de SLA.'))
                                    ->dateTime('d M Y H:i')
                                    ->since(),

                                TextEntry::make('first_responded_at')
                                    ->label(self::labelWithTip('Primera respuesta', 'Momento en que un agente respondió o tomó el ticket por primera vez. Detiene el reloj de "primera respuesta" del SLA.'))
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('Pendiente')
                                    ->color(fn (Ticket $record) => $record->first_responded_at ? 'success' : 'warning'),

                                TextEntry::make('first_response_due_at')
                                    ->label(self::labelWithTip('Vence primera respuesta', 'Objetivo de SLA para la primera respuesta. Calculado al crear el ticket usando minutos hábiles (L-V 08:00-18:00).'))
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('—')
                                    ->color(fn (Ticket $record) => $record->first_response_breached ? 'danger' : 'gray'),

                                TextEntry::make('resolution_due_at')
                                    ->label(self::labelWithTip('Vence resolución', 'Objetivo de SLA para resolver. Si resolved_at lo supera, el ticket entra en "fuera de SLA".'))
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('—')
                                    ->color(fn (Ticket $record) => $record->resolution_breached ? 'danger' : 'gray'),

                                TextEntry::make('resolved_at')
                                    ->label(self::labelWithTip('Resuelto', 'Momento en que el agente marcó el ticket como resuelto. A partir de aquí cuenta el tiempo Resuelto→Cerrado.'))
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('—'),

                                TextEntry::make('closed_at')
                                    ->label(self::labelWithTip('Cerrado', 'Confirmación del solicitante o auto-cierre del job programado tras X días sin respuesta.'))
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('—'),

                                TextEntry::make('paused_minutes')
                                    ->label(self::labelWithTip('Tiempo pausado', 'Minutos hábiles acumulados mientras el ticket estuvo en pendiente_cliente. El SLA se detiene en ese estado porque la demora no es responsabilidad del agente.'))
                                    ->state(fn (Ticket $record) => self::formatPausedMinutes($record))
                                    ->placeholder('0 min'),

                                TextEntry::make('solution_time')
                                    ->label(self::labelWithTip('Tiempo de solución', 'Trabajo efectivo del agente: minutos hábiles entre creación y resolución, descontando el tiempo pausado.'))
                                    ->state(fn (Ticket $record) => self::computeSolutionTime($record))
                                    ->placeholder('—'),

                                TextEntry::make('resolved_to_closed')
                                    ->label(self::labelWithTip('Resuelto → Cerrado', 'Horas calendario que el cliente tardó en confirmar la solución (o que esperamos hasta auto-cerrar).'))
                                    ->state(fn (Ticket $record) => self::computeResolvedToClosed($record))
                                    ->placeholder('—'),

                                IconEntry::make('first_response_breached')
                                    ->label(self::labelWithTip('¿SLA primera respuesta?', 'Rojo = SLA de primera respuesta vencido. Verde = cumplido o aún dentro de plazo.'))
                                    ->boolean()
                                    ->trueIcon('heroicon-o-exclamation-triangle')
                                    ->trueColor('danger')
                                    ->falseIcon('heroicon-o-check-circle')
                                    ->falseColor('success'),

                                IconEntry::make('resolution_breached')
                                    ->label(self::labelWithTip('¿SLA resolución?', 'Rojo = SLA de resolución vencido. Verde = cumplido o aún dentro de plazo.'))
                                    ->boolean()
                                    ->trueIcon('heroicon-o-exclamation-triangle')
                                    ->trueColor('danger')
                                    ->falseIcon('heroicon-o-check-circle')
                                    ->falseColor('success'),
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
                                    ->label(self::labelWithTip('Impacto', 'Alcance del problema: cuántos usuarios o servicios afecta.'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state?->getLabel())
                                    ->color('gray'),

                                TextEntry::make('urgency')
                                    ->label(self::labelWithTip('Urgencia', 'Qué tan rápido debe resolverse desde la perspectiva del solicitante.'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state?->getLabel())
                                    ->color('gray'),

                                TextEntry::make('status')
                                    ->label(self::labelWithTip('Estado', 'Etapa actual del ticket. En pendiente_cliente el reloj de SLA queda pausado.'))
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
                                    }),
                            ]),
                    ]),
            ]);
    }

    /**
     * Renderiza el label con un ícono ❓ inline (a la derecha del texto)
     * que muestra la definición de la métrica al hacer hover, usando el
     * tooltip nativo del navegador (title attr).
     */
    protected static function labelWithTip(string $label, string $tooltip): HtmlString
    {
        return new HtmlString(sprintf(
            '%s <span title="%s" class="ml-1 inline-flex cursor-help text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300" aria-label="%s">'
            .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">'
            .'<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />'
            .'</svg></span>',
            e($label),
            e($tooltip),
            e($tooltip),
        ));
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
