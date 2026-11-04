<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\View\FileViewFinder;
use Javaabu\Manifest\Manifest;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InertiaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // As inertia-laravel binds it for assertInertia().
        $this->app->bind('inertia.testing.view-finder', fn ($app) => new FileViewFinder(
            $app['files'],
            [resource_path('js/pages')],
            ['tsx', 'jsx', 'vue'],
        ));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(resource_path('vendor'));

        parent::tearDown();
    }

    #[Test]
    public function it_lets_inertias_testing_helpers_find_package_pages(): void
    {
        $finder = $this->app->make('inertia.testing.view-finder');

        $this->assertEquals(base_path('vendor/acme/filters/resources/js/Pages/Index.tsx'), $finder->find('filters::Index'));
        $this->assertEquals(base_path('vendor/acme/filters/resources/js/Pages/Reports/Show.tsx'), $finder->find('filters::Reports/Show'));
        $this->assertEquals(resource_path('js/pages/Dashboard.tsx'), $finder->find('Dashboard'));
    }

    #[Test]
    public function it_finds_the_apps_override_of_a_package_page_first(): void
    {
        File::ensureDirectoryExists(resource_path('vendor/filters/js/Pages'));
        File::put(resource_path('vendor/filters/js/Pages/Index.vue'), '<template />');

        $finder = $this->app->make('inertia.testing.view-finder');

        $this->assertEquals(resource_path('vendor/filters/js/Pages/Index.vue'), $finder->find('filters::Index'));
    }

    #[Test]
    public function it_lists_package_pages_with_overrides_applied(): void
    {
        File::ensureDirectoryExists(resource_path('vendor/filters/js/Pages/Reports'));
        File::put(resource_path('vendor/filters/js/Pages/Reports/Show.tsx'), 'export default () => null');

        $pages = app(Manifest::class)->pages();

        $this->assertEquals(['filters::Index', 'filters::Reports/Show'], array_keys($pages));
        $this->assertEquals(resource_path('vendor/filters/js/Pages/Reports/Show.tsx'), $pages['filters::Reports/Show']);
    }
}
