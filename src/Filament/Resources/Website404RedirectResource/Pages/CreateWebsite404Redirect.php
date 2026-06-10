<?php

namespace Apogee\Website404Redirects\Filament\Resources\Website404RedirectResource\Pages;

use Apogee\Website404Redirects\Filament\Resources\Website404RedirectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWebsite404Redirect extends CreateRecord
{
    protected static string $resource = Website404RedirectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = Website404RedirectResource::normalizeFormData($data);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        if (! isset($data['hit_count'])) {
            $data['hit_count'] = 0;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
