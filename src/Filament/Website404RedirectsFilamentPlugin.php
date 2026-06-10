<?php

namespace Apogee\Website404Redirects\Filament;

use Apogee\Website404Redirects\Filament\Resources\Website404RedirectResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class Website404RedirectsFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'website-404-redirects';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            Website404RedirectResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
    }
}
