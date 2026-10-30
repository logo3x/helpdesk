<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    public function getSubheading(): ?string
    {
        return 'Inicios de sesión y acciones de cada usuario. Usa los filtros para buscar quién hizo qué y cuándo.';
    }
}
