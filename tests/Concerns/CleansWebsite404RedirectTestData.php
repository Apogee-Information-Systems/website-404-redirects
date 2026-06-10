<?php

namespace Apogee\Website404Redirects\Tests\Concerns;

use Apogee\Website404Redirects\Models\Website404Redirect;
use Apogee\Website404Redirects\Services\Website404Redirect\RedirectCache;

/**
 * Deletes only rows created by package tests (path contains the test prefix).
 *
 * Extra safety net alongside DatabaseTransactions; test paths use the pkg-404-test marker.
 */
trait CleansWebsite404RedirectTestData
{
    public const TEST_PATH_MARKER = 'pkg-404-test';

    /** @var list<string> */
    protected array $website404RedirectTestPaths = [];

    protected function trackWebsite404RedirectPath(string $path): string
    {
        $this->website404RedirectTestPaths[] = $path;

        return $path;
    }

    protected function uniquePublicPath(string $prefix = self::TEST_PATH_MARKER): string
    {
        return $this->trackWebsite404RedirectPath(
            '/'.$prefix.'-'.strtolower(bin2hex(random_bytes(6)))
        );
    }

    protected function tearDownWebsite404RedirectTestData(): void
    {
        $marker = self::TEST_PATH_MARKER;

        Website404Redirect::query()
            ->where(function ($query) use ($marker): void {
                $query->where('path', 'like', '%'.$marker.'%')
                    ->orWhere('path', 'like', '%/blog/'.$marker.'%');
            })
            ->delete();

        app(RedirectCache::class)->forget();
    }

    protected function tearDown(): void
    {
        $this->tearDownWebsite404RedirectTestData();

        parent::tearDown();
    }
}
