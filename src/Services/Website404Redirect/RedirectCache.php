<?php

namespace Apogee\Website404Redirects\Services\Website404Redirect;

use Apogee\Website404Redirects\Models\Website404Redirect;
use Illuminate\Support\Facades\Cache;

class RedirectCache
{
    /**
     * @return array<string, array{redirect_to: string, redirect_status: int}>
     */
    public function activeRedirects(): array
    {
        $key = config('website-404-redirects.cache.key', 'website_404_redirects:active');
        $ttl = (int) config('website-404-redirects.cache.ttl', 3600);
        $store = config('website-404-redirects.cache.store');

        $callback = fn (): array => Website404Redirect::query()
            ->whereNotNull('redirect_to')
            ->where('is_ignored', false)
            ->get(['path', 'redirect_to', 'redirect_status'])
            ->mapWithKeys(fn (Website404Redirect $row) => [
                $row->path => [
                    'redirect_to' => $row->redirect_to,
                    'redirect_status' => (int) $row->redirect_status,
                ],
            ])
            ->all();

        if ($store) {
            return Cache::store($store)->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    public function lookup(string $path): ?array
    {
        $redirects = $this->activeRedirects();

        return $redirects[$path] ?? null;
    }

    public function forget(): void
    {
        $key = config('website-404-redirects.cache.key', 'website_404_redirects:active');
        $store = config('website-404-redirects.cache.store');

        if ($store) {
            Cache::store($store)->forget($key);

            return;
        }

        Cache::forget($key);
    }
}
