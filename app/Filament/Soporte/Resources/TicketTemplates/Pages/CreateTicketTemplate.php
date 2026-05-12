<?php

namespace App\Filament\Soporte\Resources\TicketTemplates\Pages;

use App\Filament\Concerns\HasAiContentActions;
use App\Filament\Soporte\Resources\TicketTemplates\TicketTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketTemplate extends CreateRecord
{
    use HasAiContentActions;

    protected static string $resource = TicketTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->generateWithAiAction(),
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
        // En Create no hay refine — el botón aparece oculto por la action.
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
