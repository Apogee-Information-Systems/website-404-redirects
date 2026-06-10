<?php

namespace Apogee\Website404Redirects\Listeners;

use Apogee\Website404Redirects\Services\Website404Redirect\Website404RedirectService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LogWebsite404Hit
{
    public function __construct(
        protected Website404RedirectService $redirectService,
    ) {}

    public function handle(NotFoundHttpException $exception, Request $request): ?Response
    {
        if (! config('website-404-redirects.enabled', true)) {
            return null;
        }

        if (! $this->redirectService->shouldLogHit($request)) {
            return null;
        }

        $this->redirectService->recordHit($request);

        return null;
    }
}
