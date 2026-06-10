<?php

namespace Apogee\Website404Redirects\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface RedirectAdminAuthorizer
{
    public function canManageRedirects(?Authenticatable $user): bool;
}
