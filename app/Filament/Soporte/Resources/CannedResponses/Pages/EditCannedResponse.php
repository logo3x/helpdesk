<?php

namespace App\Filament\Soporte\Resources\CannedResponses\Pages;

use App\Filament\Concerns\HasAiContentActions;
use App\Filament\Soporte\Resources\CannedResponses\CannedResponseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCannedResponse extends EditRecord
{
    use HasAiContentActions;

    protected static string $resource = CannedResponseResource::class;

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
        $state = $this->form->getState();

        return $state['body'] ?? null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
