<?php

namespace Apogee\Website404Redirects\Services\Website404Redirect;

use Apogee\Website404Redirects\Models\Website404Redirect;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Website404RedirectService
{
    public function __construct(
        protected PathNormalizer $pathNormalizer,
        protected RedirectCache $redirectCache,
    ) {}

    public function redirectForPath(string $path): ?RedirectResponse
    {
        if (! config('website-404-redirects.enabled', true)) {
            return null;
        }

        try {
            $normalized = $this->pathNormalizer->normalize($path);
        } catch (InvalidArgumentException) {
            return null;
        }

        $rule = $this->redirectCache->lookup($normalized);

        if ($rule === null) {
            $row = Website404Redirect::query()
                ->where('path', $normalized)
                ->whereNotNull('redirect_to')
                ->where('is_ignored', false)
                ->first(['redirect_to', 'redirect_status']);

            if ($row === null) {
                return null;
            }

            $rule = [
                'redirect_to' => $row->redirect_to,
                'redirect_status' => (int) $row->redirect_status,
            ];
        }

        return redirect()->to(
            $rule['redirect_to'],
            $rule['redirect_status'] ?? (int) config('website-404-redirects.default_redirect_status', 301)
        );
    }

    public function shouldLogHit(Request $request): bool
    {
        if (! config('website-404-redirects.enabled', true)) {
            return false;
        }

        if (! in_array($request->method(), config('website-404-redirects.log_methods', ['GET', 'HEAD']), true)) {
            return false;
        }

        if ($this->isExcluded($request)) {
            return false;
        }

        if ($request->route() === null) {
            return true;
        }

        return $this->pathMatchesLogMatchedRoutePatterns($request->getPathInfo());
    }

    public function recordHit(Request $request): void
    {
        if (! $this->shouldLogHit($request)) {
            return;
        }

        try {
            $path = $this->pathNormalizer->normalize($request->getPathInfo());
        } catch (InvalidArgumentException) {
            return;
        }

        if ($this->pathIsIgnored($path)) {
            return;
        }

        $now = now();
        $referer = $this->truncateReferer($request->headers->get('Referer'));
        $table = config('website-404-redirects.table', 'website_404_redirects');

        $updated = DB::table($table)
            ->where('path', $path)
            ->where('is_ignored', false)
            ->update([
                'hit_count' => DB::raw('hit_count + 1'),
                'last_seen_at' => $now,
                'last_referer' => $referer,
                'updated_at' => $now,
            ]);

        if ($updated > 0) {
            return;
        }

        if ($this->pathIsIgnored($path)) {
            return;
        }

        try {
            DB::table($table)->insert([
                'path' => $path,
                'hit_count' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'redirect_status' => (int) config('website-404-redirects.default_redirect_status', 301),
                'is_ignored' => false,
                'last_referer' => $referer,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            DB::table($table)
                ->where('path', $path)
                ->where('is_ignored', false)
                ->update([
                    'hit_count' => DB::raw('hit_count + 1'),
                    'last_seen_at' => $now,
                    'last_referer' => $referer,
                    'updated_at' => $now,
                ]);
        }
    }

    public function isExcluded(Request $request): bool
    {
        if (! in_array($request->method(), array_merge(
            config('website-404-redirects.redirect_methods', ['GET', 'HEAD']),
            config('website-404-redirects.log_methods', ['GET', 'HEAD'])
        ), true)) {
            return true;
        }

        return $this->pathMatchesExcludePatterns($request->getPathInfo());
    }

    public function pathMatchesExcludePatterns(string $path): bool
    {
        $checkPath = ltrim($path, '/');

        foreach (config('website-404-redirects.exclude_patterns', []) as $pattern) {
            if (Str::is($pattern, $checkPath)) {
                return true;
            }
        }

        return false;
    }

    public function pathMatchesLogMatchedRoutePatterns(string $path): bool
    {
        $checkPath = ltrim($path, '/');

        foreach (config('website-404-redirects.log_matched_route_patterns', []) as $pattern) {
            if (Str::is($pattern, $checkPath)) {
                return true;
            }
        }

        return false;
    }

    protected function pathIsIgnored(string $path): bool
    {
        return Website404Redirect::query()
            ->where('path', $path)
            ->where('is_ignored', true)
            ->exists();
    }

    protected function truncateReferer(?string $referer): ?string
    {
        if ($referer === null || $referer === '') {
            return null;
        }

        return Str::limit($referer, 2048, '');
    }

    protected function isDuplicateKeyException(QueryException $exception): bool
    {
        $code = (int) ($exception->errorInfo[1] ?? 0);

        return $code === 1062 || str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }
}
