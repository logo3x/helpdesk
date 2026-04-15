<?php

namespace App\Filament\Resources\ChatSessions\Pages;

use App\Filament\Resources\ChatSessions\ChatSessionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewChatSession extends ViewRecord
{
    protected static string $resource = ChatSessionResource::class;

    protected string $view = 'filament.resources.chat-sessions.pages.view-chat-session';

    public function getTitle(): string
    {
        return 'Conversación #'.$this->record->id;
    }

    public function getBreadcrumb(): string
    {
        return '#'.$this->record->id;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
