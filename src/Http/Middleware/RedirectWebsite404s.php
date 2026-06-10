<?php

namespace Apogee\Website404Redirects\Http\Middleware;

use Apogee\Website404Redirects\Services\Website404Redirect\Website404RedirectService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectWebsite404s
{
    public function __construct(
        protected Website404RedirectService $redirectService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('website-404-redirects.enabled', true)) {
            return $next($request);
        }

        if (! in_array($request->method(), config('website-404-redirects.redirect_methods', ['GET', 'HEAD']), true)) {
            return $next($request);
        }

        if ($this->redirectService->pathMatchesExcludePatterns($request->getPathInfo())) {
            return $next($request);
        }

        $redirect = $this->redirectService->redirectForPath($request->getPathInfo());

        if ($redirect !== null) {
            return $redirect;
        }

        return $next($request);
    }
}
