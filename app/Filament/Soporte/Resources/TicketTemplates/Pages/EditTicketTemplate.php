<?php

namespace App\Filament\Soporte\Resources\TicketTemplates\Pages;

use App\Filament\Concerns\HasAiContentActions;
use App\Filament\Soporte\Resources\TicketTemplates\TicketTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTicketTemplate extends EditRecord
{
    use HasAiContentActions;

    protected static string $resource = TicketTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->generateWithAiAction(),
            $this->refineWithAiAction(),
            DeleteAction::make()
                ->visible(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte'])),
        ];
    }

    protected function aiAssistantKind(): string
    {
        return 'ticket_template';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyAiResult(array $data): void
    {
        $this->form->fill(array_merge($this->form->getState(), array_filter([
            'subject' => $data['subject'] ?? null,
            'description' => $data['description'] ?? null,
        ])));
    }

    protected function currentAiSourceText(): ?string
    {
        $state = $this->form->getState();

        return $state['description'] ?? null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
