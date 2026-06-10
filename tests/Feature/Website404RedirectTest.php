<?php

namespace Apogee\Website404Redirects\Tests\Feature;

use Apogee\Website404Redirects\Models\Website404Redirect;
use Apogee\Website404Redirects\Services\Website404Redirect\PathNormalizer;
use Apogee\Website404Redirects\Services\Website404Redirect\RedirectCache;
use Apogee\Website404Redirects\Services\Website404Redirect\Website404RedirectService;
use Apogee\Website404Redirects\Tests\Concerns\CleansWebsite404RedirectTestData;
use Apogee\Website404Redirects\Tests\TestCase;
use Illuminate\Http\Request;

/**
 * Orchestra Testbench feature tests (P5.1). SQLite :memory: via phpunit.xml.dist.
 */
class Website404RedirectTest extends TestCase
{
    use CleansWebsite404RedirectTestData;

    private function forgetRedirectCache(): void
    {
        app(RedirectCache::class)->forget();
    }

    private function createRedirectRule(
        string $path,
        string $redirectTo,
        array $attributes = [],
    ): Website404Redirect {
        $normalizer = app(PathNormalizer::class);

        $record = Website404Redirect::query()->create(array_merge([
            'path' => $this->trackWebsite404RedirectPath($normalizer->normalize($path)),
            'redirect_to' => $normalizer->normalize($redirectTo),
            'redirect_status' => 301,
            'hit_count' => 0,
            'is_ignored' => false,
        ], $attributes));

        $this->forgetRedirectCache();

        return $record;
    }

    public function test_unknown_public_path_logs_404_with_hit_count_one(): void
    {
        $path = $this->uniquePublicPath();

        $response = $this->get($path);

        $response->assertNotFound();

        $this->assertDatabaseHas('website_404_redirects', [
            'path' => $path,
            'hit_count' => 1,
        ]);
    }

    public function test_repeat_unknown_request_increments_hit_count(): void
    {
        $path = $this->uniquePublicPath();

        $this->get($path)->assertNotFound();
        $this->get($path)->assertNotFound();

        $row = Website404Redirect::query()->where('path', $path)->first();

        $this->assertNotNull($row);
        $this->assertSame(2, $row->hit_count);
        $this->assertNotNull($row->last_seen_at);
        $this->assertNotNull($row->first_seen_at);
    }

    public function test_path_with_redirect_in_database_returns_301_without_incrementing_hits(): void
    {
        $from = $this->uniquePublicPath(self::TEST_PATH_MARKER.'-redirect-from');
        $to = $this->uniquePublicPath(self::TEST_PATH_MARKER.'-redirect-to');

        $this->createRedirectRule($from, $to, ['hit_count' => 5]);

        $response = $this->get($from);

        $response->assertRedirect($to);
        $response->assertStatus(301);

        $this->assertDatabaseHas('website_404_redirects', [
            'path' => $from,
            'hit_count' => 5,
        ]);
    }

    public function test_matched_blog_route_missing_post_logs_row(): void
    {
        $slug = self::TEST_PATH_MARKER.'-missing-'.bin2hex(random_bytes(6));
        $path = $this->trackWebsite404RedirectPath('/blog/'.$slug);
        $normalizer = app(PathNormalizer::class);
        $normalized = $normalizer->normalize($path);

        $response = $this->get($path);

        $response->assertNotFound();
        $this->assertDatabaseHas('website_404_redirects', [
            'path' => $normalized,
            'hit_count' => 1,
        ]);
    }

    public function test_matched_route_outside_log_patterns_does_not_log_row(): void
    {
        $path = $this->trackWebsite404RedirectPath(
            '/reports/'.self::TEST_PATH_MARKER.'-'.bin2hex(random_bytes(12))
        );
        $before = Website404Redirect::query()->count();

        $response = $this->get($path);

        $response->assertNotFound();
        $this->assertSame($before, Website404Redirect::query()->count());
        $this->assertDatabaseMissing('website_404_redirects', ['path' => $path]);
    }

    public function test_excluded_admin_path_does_not_log_row(): void
    {
        $path = $this->trackWebsite404RedirectPath(
            '/admin/'.self::TEST_PATH_MARKER.'-'.bin2hex(random_bytes(4))
        );
        $before = Website404Redirect::query()->count();

        $this->get($path);

        $this->assertSame($before, Website404Redirect::query()->count());
    }

    public function test_homepage_path_is_excluded_from_logging(): void
    {
        $service = app(Website404RedirectService::class);

        $this->assertTrue($service->pathMatchesExcludePatterns('/'));
        $this->assertFalse($service->shouldLogHit(Request::create('/', 'GET')));
    }

    public function test_path_normalization_deduplicates_mixed_case_requests(): void
    {
        $base = self::TEST_PATH_MARKER.'-normalize-'.bin2hex(random_bytes(4));
        $lower = '/'.strtolower($base);
        $mixed = '/'.ucfirst(strtolower($base));

        $this->trackWebsite404RedirectPath($lower);
        $this->trackWebsite404RedirectPath($mixed);

        $this->get($mixed)->assertNotFound();
        $this->get($lower)->assertNotFound();

        $this->assertSame(
            1,
            Website404Redirect::query()->where('path', $lower)->count()
        );
        $this->assertDatabaseHas('website_404_redirects', [
            'path' => $lower,
            'hit_count' => 2,
        ]);
    }

    public function test_ignored_path_returns_404_without_logging_or_redirect(): void
    {
        $path = $this->uniquePublicPath(self::TEST_PATH_MARKER.'-ignored');
        $normalizer = app(PathNormalizer::class);
        $normalized = $normalizer->normalize($path);

        Website404Redirect::query()->create([
            'path' => $normalized,
            'hit_count' => 0,
            'is_ignored' => true,
            'redirect_to' => $normalizer->normalize($this->uniquePublicPath(self::TEST_PATH_MARKER.'-ignored-target')),
            'redirect_status' => 301,
        ]);

        $this->forgetRedirectCache();

        $response = $this->get($path);

        $response->assertNotFound();
        $this->assertDatabaseHas('website_404_redirects', [
            'path' => $normalized,
            'hit_count' => 0,
        ]);
    }

    public function test_manually_created_redirect_works_without_prior_404_hit(): void
    {
        $from = $this->uniquePublicPath(self::TEST_PATH_MARKER.'-manual-from');
        $to = $this->uniquePublicPath(self::TEST_PATH_MARKER.'-manual-to');

        $this->createRedirectRule($from, $to);

        $response = $this->get($from);

        $response->assertRedirect($to);
        $response->assertStatus(301);
        $this->assertDatabaseHas('website_404_redirects', [
            'path' => $from,
            'hit_count' => 0,
        ]);
    }

    public function test_head_unknown_path_logs_404(): void
    {
        $path = $this->uniquePublicPath(self::TEST_PATH_MARKER.'-head-unknown');

        $response = $this->head($path);

        $response->assertNotFound();
        $this->assertDatabaseHas('website_404_redirects', [
            'path' => $path,
            'hit_count' => 1,
        ]);
    }

    public function test_head_redirect_path_returns_301(): void
    {
        $from = $this->uniquePublicPath(self::TEST_PATH_MARKER.'-head-from');
        $to = $this->uniquePublicPath(self::TEST_PATH_MARKER.'-head-to');

        $this->createRedirectRule($from, $to);

        $response = $this->head($from);

        $response->assertRedirect($to);
        $response->assertStatus(301);
    }

    public function test_short_slug_redirects_to_canonical_path(): void
    {
        $normalizer = app(PathNormalizer::class);
        $shortSlug = self::TEST_PATH_MARKER.'-short-'.bin2hex(random_bytes(4));
        $canonicalSlug = self::TEST_PATH_MARKER.'-canonical-'.bin2hex(random_bytes(4));
        $from = $this->trackWebsite404RedirectPath('/blog/'.$shortSlug);
        $to = $this->trackWebsite404RedirectPath('/blog/'.$canonicalSlug);

        Website404Redirect::query()->create([
            'path' => $normalizer->normalize($from),
            'redirect_to' => $normalizer->normalize($to),
            'redirect_status' => 301,
        ]);

        $this->forgetRedirectCache();

        $response = $this->get($from);

        $response->assertRedirect($to);
        $response->assertStatus(301);
    }
}
