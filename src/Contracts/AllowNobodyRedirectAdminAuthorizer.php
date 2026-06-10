<?php

namespace Apogee\Website404Redirects\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

class AllowNobodyRedirectAdminAuthorizer implements RedirectAdminAuthorizer
{
    public function canManageRedirects(?Authenticatable $user): bool
    {
        return false;
    }
}
