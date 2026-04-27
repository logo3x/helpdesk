<?php

namespace App\Filament\Soporte\Resources\TicketTemplates;

use App\Filament\Soporte\Resources\TicketTemplates\Pages\CreateTicketTemplate;
use App\Filament\Soporte\Resources\TicketTemplates\Pages\EditTicketTemplate;
use App\Filament\Soporte\Resources\TicketTemplates\Pages\ListTicketTemplates;
use App\Filament\Soporte\Resources\TicketTemplates\Schemas\TicketTemplateForm;
use App\Filament\Soporte\Resources\TicketTemplates\Tables\TicketTemplatesTable;
use App\Models\TicketTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketTemplateResource extends Resource
{
    protected static ?string $model = TicketTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $modelLabel = 'Plantilla para Ticket';

    protected static ?string $pluralModelLabel = 'Plantillas para Tickets';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return TicketTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketTemplates::route('/'),
            'create' => CreateTicketTemplate::route('/create'),
            'edit' => EditTicketTemplate::route('/{record}/edit'),
        ];
    }

    /**
     * Templates are filtered by department via their category.
     * Super_admin/admin see all; others only those whose category
     * belongs to their own department.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && ! $user->hasAnyRole(['super_admin', 'admin']) && $user->department_id) {
            $query->whereHas('category', fn ($q) => $q->where('department_id', $user->department_id));
        }

        return $query;
    }
}
