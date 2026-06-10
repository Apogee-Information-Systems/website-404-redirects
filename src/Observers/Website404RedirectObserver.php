<?php

namespace Apogee\Website404Redirects\Observers;

use Apogee\Website404Redirects\Models\Website404Redirect;
use Apogee\Website404Redirects\Services\Website404Redirect\RedirectCache;

class Website404RedirectObserver
{
    public function __construct(
        protected RedirectCache $redirectCache,
    ) {}

    public function saved(Website404Redirect $redirect): void
    {
        $this->redirectCache->forget();
    }

    public function deleted(Website404Redirect $redirect): void
    {
        $this->redirectCache->forget();
    }
}
