<?php

namespace Apogee\Website404Redirects\Services\Website404Redirect;

use InvalidArgumentException;

class PathNormalizer
{
    public function normalize(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?? $path;
        $path = trim($path);

        if ($path === '') {
            $path = '/';
        }

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/') ?: '/';
        }

        if (config('website-404-redirects.normalize_lowercase', true)) {
            $path = $this->lowercasePath($path);
        }

        $maxLength = (int) config('website-404-redirects.max_path_length', 512);

        if (strlen($path) > $maxLength) {
            throw new InvalidArgumentException(
                __('Path exceeds maximum length of :max characters.', ['max' => $maxLength])
            );
        }

        return $path;
    }

    protected function lowercasePath(string $path): string
    {
        if ($path === '/') {
            return $path;
        }

        $segments = explode('/', trim($path, '/'));
        $segments = array_map(static fn (string $segment) => strtolower($segment), $segments);

        return '/'.implode('/', $segments);
    }
}
