<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\View\FileViewFinder;
use Inertia\Inertia;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Inertia\Testing\AssertableInertia;
use Javaabu\Manifest\Manifest;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;

class InertiaTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), InertiaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // v3 reads inertia.pages, v2 inertia.testing.
        $app['config']->set('inertia.pages.paths', [resource_path('js/pages')]);
        $app['config']->set('inertia.testing.page_paths', [resource_path('js/pages')]);
    }

    protected function defineRoutes($router): void
    {
        Route::get('pages/{component}', function (string $component) {
            Inertia::setRootView('inertia');

            return Inertia::render(str_replace('.', '/', $component));
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(resource_path('vendor'));

        parent::tearDown();
    }

    /**
     * The binding the installed inertia-laravel checks pages with.
     */
    protected function finder(): FileViewFinder
    {
        return $this->app->bound('inertia.view-finder')
            ? $this->app->make('inertia.view-finder')
            : $this->app->make('inertia.testing.view-finder');
    }

    protected function inertia(string $component): TestResponse
    {
        return $this->get('pages/' . str_replace('/', '.', $component));
    }

    #[Test]
    public function it_lets_assert_inertia_find_package_pages(): void
    {
        $this->inertia('filters::Index')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('filters::Index'));

        $this->inertia('filters::Reports/Show')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('filters::Reports/Show'));

        $this->inertia('Dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard'));
    }

    #[Test]
    public function it_still_fails_assert_inertia_for_a_missing_package_page(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Inertia page component file [filters::Missing] does not exist.');

        $this->inertia('filters::Missing')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('filters::Missing'));
    }

    #[Test]
    public function it_lets_inertia_render_package_pages_when_it_checks_that_pages_exist(): void
    {
        config(['inertia.pages.ensure_pages_exist' => true]);

        $this->inertia('filters::Index')->assertOk();
    }

    #[Test]
    public function it_finds_package_pages_with_inertias_view_finder(): void
    {
        $finder = $this->finder();

        $this->assertEquals(base_path('vendor/acme/filters/resources/js/Pages/Index.tsx'), $finder->find('filters::Index'));
        $this->assertEquals(base_path('vendor/acme/filters/resources/js/Pages/Reports/Show.tsx'), $finder->find('filters::Reports/Show'));
        $this->assertEquals(resource_path('js/pages/Dashboard.tsx'), $finder->find('Dashboard'));
    }

    #[Test]
    public function it_supports_inertia_v2s_testing_view_finder(): void
    {
        // As inertia-laravel v2 binds it for assertInertia().
        $this->app->bind('inertia.testing.view-finder', fn ($app) => new FileViewFinder(
            $app['files'],
            [resource_path('js/pages')],
            ['tsx', 'jsx', 'vue'],
        ));

        $finder = $this->app->make('inertia.testing.view-finder');

        $this->assertEquals(base_path('vendor/acme/filters/resources/js/Pages/Index.tsx'), $finder->find('filters::Index'));
    }

    #[Test]
    public function it_finds_the_apps_override_of_a_package_page_first(): void
    {
        File::ensureDirectoryExists(resource_path('vendor/filters/js/Pages'));
        File::put(resource_path('vendor/filters/js/Pages/Index.vue'), '<template />');

        $this->assertEquals(resource_path('vendor/filters/js/Pages/Index.vue'), $this->finder()->find('filters::Index'));

        $this->inertia('filters::Index')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('filters::Index'));
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
