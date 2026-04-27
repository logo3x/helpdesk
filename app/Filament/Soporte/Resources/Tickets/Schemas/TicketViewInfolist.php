<?php

namespace App\Filament\Soporte\Resources\Tickets\Schemas;

use App\Models\Ticket;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

/**
 * Vista del ticket pensada para que el agente identifique el caso y
 * su seguimiento en pocos segundos:
 *
 *   1. "Resumen" arriba con un grid compacto: solicitante, asignado,
 *      categoría, prioridad, SLA usado, etc. → escaneo rápido.
 *   2. "Descripción del problema" prominente con el Markdown del
 *      reporte original.
 *   3. "SLA" sección detallada (colapsable) con los timestamps.
 *   4. La conversación (RelationManager) queda debajo, ya rediseñada.
 */
class TicketViewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ── Card de un vistazo ─────────────────────────────────
                Section::make('Resumen del caso')
                    ->icon('heroicon-o-identification')
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

                // ── Descripción del problema ───────────────────────────
                // Abierta por defecto (es lo que el agente necesita
                // leer primero) pero colapsable para que cuando ya la
                // conozca pueda dejar la pantalla limpia y enfocarse
                // en la conversación.
                Section::make('Descripción del problema')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->markdown()
                            ->columnSpanFull(),
                    ]),

                // ── Adjuntos (solo si hay) ─────────────────────────────
                Section::make('Adjuntos')
                    ->icon('heroicon-o-paper-clip')
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

                // ── Tiempos / SLA ──────────────────────────────────────
                Section::make('SLA y tiempos')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Creado')
                                    ->dateTime('d M Y H:i')
                                    ->since(),

                                TextEntry::make('first_responded_at')
                                    ->label('Primera respuesta')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('Pendiente')
                                    ->color(fn (Ticket $record) => $record->first_responded_at ? 'success' : 'warning'),

                                TextEntry::make('first_response_due_at')
                                    ->label('Vence primera respuesta')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('—')
                                    ->color(fn (Ticket $record) => $record->first_response_breached ? 'danger' : 'gray')
                                    ->helperText(fn (Ticket $record) => $record->first_response_breached ? 'SLA vencido' : null),

                                TextEntry::make('resolution_due_at')
                                    ->label('Vence resolución')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('—')
                                    ->color(fn (Ticket $record) => $record->resolution_breached ? 'danger' : 'gray')
                                    ->helperText(fn (Ticket $record) => $record->resolution_breached ? 'SLA vencido' : null),

                                TextEntry::make('resolved_at')
                                    ->label('Resuelto')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('—'),

                                TextEntry::make('closed_at')
                                    ->label('Cerrado')
                                    ->dateTime('d M Y H:i')
                                    ->placeholder('—'),

                                IconEntry::make('first_response_breached')
                                    ->label('¿SLA primera respuesta vencido?')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-exclamation-triangle')
                                    ->trueColor('danger')
                                    ->falseIcon('heroicon-o-check-circle')
                                    ->falseColor('success'),

                                IconEntry::make('resolution_breached')
                                    ->label('¿SLA resolución vencido?')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-exclamation-triangle')
                                    ->trueColor('danger')
                                    ->falseIcon('heroicon-o-check-circle')
                                    ->falseColor('success'),

                                TextEntry::make('paused_minutes')
                                    ->label('Tiempo pausado (min)')
                                    ->numeric()
                                    ->placeholder('0'),
                            ]),
                    ]),

                // ── Detalles técnicos (impacto/urgencia) ───────────────
                Section::make('Clasificación técnica')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 3])
                            ->schema([
                                TextEntry::make('impact')
                                    ->label('Impacto')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state?->getLabel())
                                    ->color('gray'),

                                TextEntry::make('urgency')
                                    ->label('Urgencia')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state?->getLabel())
                                    ->color('gray'),

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
                                    }),
                            ]),
                    ]),
            ]);
    }
}
