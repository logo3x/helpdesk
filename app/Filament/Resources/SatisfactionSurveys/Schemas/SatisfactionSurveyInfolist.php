<?php

namespace App\Filament\Resources\SatisfactionSurveys\Schemas;

use App\Models\SatisfactionSurvey;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SatisfactionSurveyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del ticket')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('ticket.number')
                            ->label('Ticket')
                            ->weight('bold'),

                        TextEntry::make('ticket.subject')
                            ->label('Asunto')
                            ->columnSpan(2),

                        TextEntry::make('user.name')
                            ->label('Usuario'),

                        TextEntry::make('ticket.department.name')
                            ->label('Departamento')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('responded_at')
                            ->label('Respondida')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Pendiente'),
                    ]),

                Section::make('Calificaciones por dimensión')
                    ->schema(function ($record): array {
                        if ($record->isPending()) {
                            return [
                                TextEntry::make('status_pending')
                                    ->label('')
                                    ->default('⏳ El usuario aún no ha respondido esta encuesta.')
                                    ->color('warning'),
                            ];
                        }

                        $entries = [];
                        foreach (SatisfactionSurvey::DIMENSIONS as $field => $label) {
                            $entries[] = TextEntry::make($field)
                                ->label($label)
                                ->formatStateUsing(fn ($state) => $state
                                    ? str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state)." ({$state}/5)"
                                    : '—')
                                ->color(fn ($state) => match (true) {
                                    (int) $state >= 4 => 'success',
                                    (int) $state === 3 => 'warning',
                                    (int) $state > 0 => 'danger',
                                    default => 'gray',
                                });
                        }

                        $entries[] = TextEntry::make('average_rating')
                            ->label('Promedio general')
                            ->state(fn ($record) => number_format($record->averageRating() ?? 0, 2).' / 5')
                            ->weight('bold')
                            ->color(fn ($record) => match (true) {
                                ($record->averageRating() ?? 0) >= 4 => 'success',
                                ($record->averageRating() ?? 0) >= 3 => 'warning',
                                default => 'danger',
                            });

                        return $entries;
                    })
                    ->columns(2),

                Section::make('Comentario del usuario')
                    ->schema([
                        TextEntry::make('comment')
                            ->label('')
                            ->placeholder('Sin comentario.')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(fn ($record) => ! $record->comment),
            ]);
    }
}
