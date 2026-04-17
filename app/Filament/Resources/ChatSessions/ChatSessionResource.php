<?php

namespace App\Filament\Resources\ChatSessions;

use App\Filament\Resources\ChatSessions\Pages\ListChatSessions;
use App\Filament\Resources\ChatSessions\Pages\ViewChatSession;
use App\Filament\Resources\ChatSessions\Tables\ChatSessionsTable;
use App\Models\ChatSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChatSessionResource extends Resource
{
    protected static ?string $model = ChatSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Conversaciones chatbot';

    protected static ?string $modelLabel = 'Conversación';

    protected static ?string $pluralModelLabel = 'Conversaciones';

    protected static string|\UnitEnum|null $navigationGroup = 'Auditoría';

    protected static ?int $navigationSort = 90;

    public static function table(Table $table): Table
    {
        return ChatSessionsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChatSessions::route('/'),
            'view' => ViewChatSession::route('/{record}'),
        ];
    }
}
