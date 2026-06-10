<?php

namespace Apogee\Website404Redirects\Services\Website404Redirect;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RedirectTargetValidator
{
    public function __construct(
        protected PathNormalizer $pathNormalizer,
    ) {}

    /**
     * @throws ValidationException
     */
    public function validate(?string $target, bool $required = false): ?string
    {
        if ($target === null || trim($target) === '') {
            if ($required) {
                throw ValidationException::withMessages([
                    'redirect_to' => __('A redirect target is required.'),
                ]);
            }

            return null;
        }

        $target = trim($target);

        if (preg_match('/^\s*javascript:/i', $target) || preg_match('/^\s*data:/i', $target)) {
            throw ValidationException::withMessages([
                'redirect_to' => __('Invalid redirect URL.'),
            ]);
        }

        if (str_starts_with($target, '//')) {
            throw ValidationException::withMessages([
                'redirect_to' => __('Redirect URLs must be a site path (starting with /) or an allowed absolute URL.'),
            ]);
        }

        if (preg_match('#^https?://#i', $target)) {
            if (! config('website-404-redirects.allow_external_redirects', false)) {
                throw ValidationException::withMessages([
                    'redirect_to' => __('External redirects are disabled. Use a path starting with /.'),
                ]);
            }

            $host = parse_url($target, PHP_URL_HOST);
            $allowed = config('website-404-redirects.allowed_external_hosts', []);

            if ($host === null || $host === '' || ! in_array($host, $allowed, true)) {
                throw ValidationException::withMessages([
                    'redirect_to' => __('This external host is not allowed.'),
                ]);
            }

            return $target;
        }

        if (! str_starts_with($target, '/')) {
            throw ValidationException::withMessages([
                'redirect_to' => __('Redirect target must start with / or be an allowed https URL.'),
            ]);
        }

        try {
            return $this->pathNormalizer->normalize($target);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'redirect_to' => $exception->getMessage(),
            ]);
        }
    }
}
