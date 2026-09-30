<?php

namespace App\Filament\Resources\SlaConfigs\Pages;

use App\Enums\TicketPriority;
use App\Filament\Resources\SlaConfigs\SlaConfigResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSlaConfig extends CreateRecord
{
    protected static string $resource = SlaConfigResource::class;

    /**
     * Permite llegar desde el Reporte SLA con el departamento y la
     * prioridad ya elegidos (?department_id=..&priority=..).
     */
    protected function fillForm(): void
    {
        parent::fillForm();

        $prefill = array_filter([
            'department_id' => request()->integer('department_id') ?: null,
            'priority' => TicketPriority::tryFrom((string) request()->query('priority'))?->value,
        ]);

        if ($prefill !== []) {
            $this->form->fill([...$this->form->getRawState(), ...$prefill]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
