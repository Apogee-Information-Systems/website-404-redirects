<?php

namespace Apogee\Website404Redirects\Filament\Resources\Website404RedirectResource\Pages;

use Apogee\Website404Redirects\Filament\Resources\Website404RedirectResource;
use Apogee\Website404Redirects\Models\Website404Redirect;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWebsite404Redirect extends ViewRecord
{
    protected static string $resource = Website404RedirectResource::class;

    public function getHeading(): string
    {
        /** @var Website404Redirect $record */
        $record = $this->record;

        return $record->path;
    }

    public function getSubheading(): ?string
    {
        /** @var Website404Redirect $record */
        $record = $this->record;

        $parts = [
            Website404RedirectResource::statusLabel($record),
            $record->hit_count.' '.($record->hit_count === 1 ? 'hit' : 'hits'),
        ];

        if (filled($record->redirect_to)) {
            $parts[] = '→ '.$record->redirect_to;
        }

        return implode(' · ', $parts);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewOnSite')
                ->label('View on site')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => rtrim((string) config('app.url'), '/').$this->record->path)
                ->openUrlInNewTab(),

            Actions\EditAction::make(),

            Actions\DeleteAction::make()
                ->requiresConfirmation(),
        ];
    }
}
