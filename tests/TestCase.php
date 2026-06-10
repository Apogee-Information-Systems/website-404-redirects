<?php

namespace Apogee\Website404Redirects\Tests;

use Apogee\Website404Redirects\Website404RedirectsServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use DatabaseTransactions;

    protected function getPackageProviders($app): array
    {
        return [
            Website404RedirectsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', ':memory:'),
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function defineRoutes($app): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/blog/{slug}', fn () => abort(404));
            Route::get('/reports/{token}', fn () => abort(404));
        });
    }
}
