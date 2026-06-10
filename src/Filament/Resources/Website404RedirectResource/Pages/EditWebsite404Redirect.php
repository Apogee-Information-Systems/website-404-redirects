<?php

namespace Apogee\Website404Redirects\Filament\Resources\Website404RedirectResource\Pages;

use Apogee\Website404Redirects\Filament\Resources\Website404RedirectResource;
use Apogee\Website404Redirects\Models\Website404Redirect;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWebsite404Redirect extends EditRecord
{
    protected static string $resource = Website404RedirectResource::class;

    public function getTitle(): string
    {
        /** @var Website404Redirect $record */
        $record = $this->record;

        return $record->path;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = Website404RedirectResource::normalizeFormData($data);

        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
