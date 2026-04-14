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

class TicketTemplateResource extends Resource
{
    protected static ?string $model = TicketTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $modelLabel = 'Plantilla';

    protected static ?string $pluralModelLabel = 'Plantillas';

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
}
