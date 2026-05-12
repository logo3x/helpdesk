<?php

namespace App\Filament\Soporte\Resources\CannedResponses\Pages;

use App\Filament\Concerns\HasAiContentActions;
use App\Filament\Soporte\Resources\CannedResponses\CannedResponseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCannedResponse extends CreateRecord
{
    use HasAiContentActions;

    protected static string $resource = CannedResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->generateWithAiAction(),
        ];
    }

    protected function aiAssistantKind(): string
    {
        return 'canned_response';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyAiResult(array $data): void
    {
        $this->form->fill(array_merge($this->form->getState(), array_filter([
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
        ])));
    }

    protected function currentAiSourceText(): ?string
    {
        return null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
