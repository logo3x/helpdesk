<?php

namespace App\Filament\Soporte\Resources\KbArticles\Pages;

use App\Filament\Soporte\Resources\KbArticles\KbArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKbArticles extends ListRecords
{
    protected static string $resource = KbArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
