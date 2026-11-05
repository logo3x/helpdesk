<?php

namespace App\Filament\Soporte\Resources\Tickets\Schemas;

use App\Filament\Concerns\HasHelpLabels;
use App\Filament\Support\SlaDefinitions;
use App\Models\Ticket;
use App\Services\SlaService;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Vista del ticket en UNA columna (filas a todo el ancho), para leer
 * cómodo de arriba abajo:
 *
 *   1. Resumen del caso          — personas, categoría, prioridad.
 *   2. Descripción del problema  — el reporte original.
 *   3. Conversación              — estilo chat, con notas internas y eventos.
 *   4. Adjuntos                  — solo si hay.
 *   5. SLA y tiempos             (colapsado) — métricas con "?" de ayuda
 *                                   (textos en SlaDefinitions).
 *   6. Clasificación técnica     (colapsado) — impacto/urgencia/estado.
 *
 * Se usa en la página del ticket y en el modal "Ver" de la tabla.
 */
class TicketViewInfolist
{
    use HasHelpLabels;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                ...array_values(self::sections()),
            ]);
    }

    /**
     * Secciones de la vista del ticket, reutilizadas por la página Ver,
     * el modal "Ver" de la lista y la página Editar.
     *
     * @return array<string, Section>
     */
    public static function sections(): array
    {
        return [
            'summary' => Section::make('Resumen del caso')
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

            'description' => Section::make('Descripción del problema')
                ->icon('heroicon-o-document-text')
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    // Quién/cuándo/origen, resumen destacado y, si viene del
                    // asistente, la transcripción plegada.
                    ViewEntry::make('description')
                        ->hiddenLabel()
                        ->view('filament.soporte.tickets.description')
                        ->columnSpanFull(),
                ]),

            'conversation' => Section::make('Conversación')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->description('Mensajes con el solicitante, notas internas y eventos del ticket, del más antiguo al más reciente.')
                ->schema([
                    ViewEntry::make('conversation')
                        ->hiddenLabel()
                        ->view('filament.soporte.tickets.conversation'),
                ]),

            'attachments' => Section::make('Adjuntos')
                ->icon('heroicon-o-paper-clip')
                ->collapsible()
                ->columnSpanFull()
                ->visible(fn (Ticket $record) => $record->getMedia('attachments')->count() > 0)
                ->schema([
                    ViewEntry::make('attachments_list')
                        ->hiddenLabel()
                        ->view('filament.soporte.tickets.attachments')
                        ->columnSpanFull(),
                ]),

            'sla' => Section::make('SLA y tiempos')
                ->icon('heroicon-o-clock')
                ->collapsible()
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                        ->schema([
                            TextEntry::make('created_at')
                                ->label(static::helpLabel('Creado', SlaDefinitions::help('created')))
                                ->dateTime('d M Y H:i')
                                ->since(),

                            TextEntry::make('first_responded_at')
                                ->label(static::helpLabel('Primera respuesta', SlaDefinitions::help('first_response')))
                                ->dateTime('d M Y H:i')
                                ->placeholder('Pendiente')
                                ->color(fn (Ticket $record) => $record->first_responded_at ? 'success' : 'warning'),

                            TextEntry::make('first_response_due_at')
                                ->label(static::helpLabel('Vence primera respuesta', SlaDefinitions::help('first_response_due')))
                                ->dateTime('d M Y H:i')
                                ->placeholder('—')
                                ->color(fn (Ticket $record) => $record->first_response_breached ? 'danger' : 'gray'),

                            TextEntry::make('resolution_due_at')
                                ->label(static::helpLabel('Vence resolución', SlaDefinitions::help('resolution_due')))
                                ->dateTime('d M Y H:i')
                                ->placeholder('—')
                                ->color(fn (Ticket $record) => $record->resolution_breached ? 'danger' : 'gray'),

                            TextEntry::make('resolved_at')
                                ->label(static::helpLabel('Resuelto', SlaDefinitions::help('resolved')))
                                ->dateTime('d M Y H:i')
                                ->placeholder('—'),

                            TextEntry::make('closed_at')
                                ->label(static::helpLabel('Cerrado', SlaDefinitions::help('closed')))
                                ->dateTime('d M Y H:i')
                                ->placeholder('—'),

                            TextEntry::make('paused_minutes')
                                ->label(static::helpLabel('Tiempo pausado', SlaDefinitions::help('paused')))
                                ->state(fn (Ticket $record) => self::formatPausedMinutes($record))
                                ->placeholder('0 min'),

                            TextEntry::make('solution_time')
                                ->label(static::helpLabel('Tiempo de solución', SlaDefinitions::help('solution_time')))
                                ->state(fn (Ticket $record) => self::computeSolutionTime($record))
                                ->placeholder('—'),

                            TextEntry::make('resolved_to_closed')
                                ->label(static::helpLabel('Resuelto → Cerrado', SlaDefinitions::help('resolved_to_closed')))
                                ->state(fn (Ticket $record) => self::computeResolvedToClosed($record))
                                ->placeholder('—'),

                            IconEntry::make('first_response_breached')
                                ->label(static::helpLabel('¿SLA primera respuesta?', SlaDefinitions::help('first_response_breached')))
                                ->boolean()
                                ->trueIcon('heroicon-o-exclamation-triangle')
                                ->trueColor('danger')
                                ->falseIcon('heroicon-o-check-circle')
                                ->falseColor('success'),

                            IconEntry::make('resolution_breached')
                                ->label(static::helpLabel('¿SLA resolución?', SlaDefinitions::help('resolution_breached')))
                                ->boolean()
                                ->trueIcon('heroicon-o-exclamation-triangle')
                                ->trueColor('danger')
                                ->falseIcon('heroicon-o-check-circle')
                                ->falseColor('success'),
                        ]),
                ]),

            'classification' => Section::make('Clasificación técnica')
                ->icon('heroicon-o-adjustments-horizontal')
                ->collapsible()
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 3])
                        ->schema([
                            TextEntry::make('impact')
                                ->label(static::helpLabel('Impacto', SlaDefinitions::help('impact')))
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state?->getLabel())
                                ->color('gray'),

                            TextEntry::make('urgency')
                                ->label(static::helpLabel('Urgencia', SlaDefinitions::help('urgency')))
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state?->getLabel())
                                ->color('gray'),

                            TextEntry::make('status')
                                ->label(static::helpLabel('Estado', SlaDefinitions::help('status')))
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
        ];
    }

    protected static function formatPausedMinutes(Ticket $ticket): string
    {
        $minutes = (int) $ticket->paused_minutes;

        if ($ticket->paused_at) {
            $live = app(SlaService::class)->businessMinutesBetween($ticket->paused_at, now());
            $minutes += $live;
        }

        return SlaService::formatMinutes($minutes);
    }

    protected static function computeSolutionTime(Ticket $ticket): ?string
    {
        if ($ticket->resolved_at === null) {
            return null;
        }

        $total = app(SlaService::class)
            ->businessMinutesBetween($ticket->created_at, $ticket->resolved_at);

        return SlaService::formatMinutes($total - (int) $ticket->paused_minutes);
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
