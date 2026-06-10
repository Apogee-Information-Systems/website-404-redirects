<?php

namespace Apogee\Website404Redirects;

use Apogee\Website404Redirects\Contracts\AllowNobodyRedirectAdminAuthorizer;
use Apogee\Website404Redirects\Contracts\RedirectAdminAuthorizer;
use Apogee\Website404Redirects\Http\Middleware\RedirectWebsite404s;
use Apogee\Website404Redirects\Listeners\LogWebsite404Hit;
use Apogee\Website404Redirects\Models\Website404Redirect;
use Apogee\Website404Redirects\Observers\Website404RedirectObserver;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Website404RedirectsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/website-404-redirects.php', 'website-404-redirects');

        $this->app->bind(RedirectAdminAuthorizer::class, AllowNobodyRedirectAdminAuthorizer::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/website-404-redirects.php' => config_path('website-404-redirects.php'),
            ], 'website-404-redirects-config');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Website404Redirect::observe(Website404RedirectObserver::class);

        $this->registerRedirectMiddleware();
        $this->registerNotFoundLogging();
    }

    protected function registerRedirectMiddleware(): void
    {
        $this->app->booted(function (): void {
            $router = $this->app['router'];
            $router->prependMiddlewareToGroup('web', RedirectWebsite404s::class);

            $kernel = $this->app->make(Kernel::class);

            if ($kernel instanceof HttpKernel) {
                $kernel->prependMiddleware(RedirectWebsite404s::class);
            }
        });
    }

    protected function registerNotFoundLogging(): void
    {
        $this->app->booted(function (): void {
            $this->app->make(ExceptionHandler::class)->renderable(
                function (NotFoundHttpException $exception, Request $request) {
                    return $this->app->make(LogWebsite404Hit::class)->handle($exception, $request);
                }
            );
        });
    }
}
