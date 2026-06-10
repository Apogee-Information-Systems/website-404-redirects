<?php

namespace Apogee\Website404Redirects\Filament\Resources\Website404RedirectResource\Pages;

use Apogee\Website404Redirects\Filament\Resources\Website404RedirectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWebsite404Redirects extends ListRecords
{
    protected static string $resource = Website404RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn (): bool => Website404RedirectResource::canCreate()),
        ];
    }
}
