<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Javaabu\Manifest\Tests\Support\InteractsWithVite;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DistTest extends TestCase
{
    use InteractsWithVite;

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('vendor'));

        parent::tearDown();
    }

    protected function defineRoutes($router): void
    {
        $router->get('/widgets', fn () => view('widgets'));
    }

    #[Test]
    public function it_falls_back_to_a_packages_prebuilt_assets_without_an_app_build(): void
    {
        $html = $this->get('/widgets')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="stylesheet" href="http://localhost/vendor/manifest/widgets/assets/theme-D1st0001.css" />', $html);
        $this->assertStringContainsString('<script type="module" src="http://localhost/vendor/manifest/widgets/assets/core-D1st0002.js"></script>', $html);
        $this->assertStringContainsString('<script type="module" src="http://localhost/vendor/manifest/widgets/assets/chart-D1st0003.js"></script>', $html);
        $this->assertStringContainsString('href="http://localhost/vendor/manifest/widgets/assets/chart-D1st0004.css"', $html);
    }

    #[Test]
    public function it_prefers_the_apps_build_when_it_has_the_asset(): void
    {
        $this->useViteBuild();

        $html = $this->get('/widgets')->getContent();

        $this->assertStringContainsString('http://localhost/build/assets/widgets-chart-abc123.js', $html);
        $this->assertStringNotContainsString('vendor/manifest/widgets', $html);
    }

    #[Test]
    public function it_uses_the_dev_server_when_vite_is_running_hot(): void
    {
        $this->useViteDevServer();

        $html = $this->get('/widgets')->getContent();

        $this->assertStringContainsString('http://localhost:5173/vendor/acme/widgets/resources/js/chart.ts', $html);
    }

    #[Test]
    public function it_can_always_or_never_use_prebuilt_assets(): void
    {
        $this->useViteBuild();

        config(['manifest.dist' => 'always']);

        $this->assertStringContainsString('vendor/manifest/widgets/assets/chart-D1st0003.js', $this->get('/widgets')->getContent());

        config(['manifest.dist' => 'never']);

        $this->assertStringNotContainsString('vendor/manifest/widgets', $this->get('/widgets')->getContent());
    }

    #[Test]
    public function it_builds_an_overridden_asset_with_the_apps_vite(): void
    {
        File::ensureDirectoryExists(resource_path('vendor/widgets/js'));
        File::put(resource_path('vendor/widgets/js/chart.ts'), 'export {}');

        $this->useViteBuild();

        config(['manifest.dist' => 'always']);

        $html = $this->get('/widgets')->getContent();

        $this->assertStringContainsString('build/assets/widgets-chart-abc123.js', $html);
        $this->assertStringNotContainsString('chart-D1st0003.js', $html);
        $this->assertStringContainsString('vendor/manifest/widgets/assets/core-D1st0002.js', $html);
    }

    #[Test]
    public function it_uses_the_csp_nonce_and_a_custom_url(): void
    {
        config(['manifest.dist_url' => 'static/packages']);

        Vite::useCspNonce('ނޮންސް');

        $html = $this->get('/widgets')->getContent();

        $this->assertStringContainsString('<script type="module" src="http://localhost/static/packages/widgets/assets/core-D1st0002.js" nonce="ނޮންސް"></script>', $html);
    }

    #[Test]
    public function it_serves_prebuilt_files_through_a_route_until_they_are_published(): void
    {
        $response = $this->get('/vendor/manifest/widgets/assets/core-D1st0002.js')->assertOk();

        $this->assertEquals('text/javascript; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('widget', $response->getFile()->getContent());

        $this->get('/vendor/manifest/widgets/assets/theme-D1st0001.css')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=utf-8');
    }

    #[Test]
    public function it_never_serves_files_outside_the_dist_folder(): void
    {
        $this->get('/vendor/manifest/widgets/../src/WidgetsServiceProvider.php')->assertNotFound();
        $this->get('/vendor/manifest/widgets/%2e%2e/src/WidgetsServiceProvider.php')->assertNotFound();
        $this->get('/vendor/manifest/widgets/assets/missing.js')->assertNotFound();
        $this->get('/vendor/manifest/filters/assets/core.js')->assertNotFound();
        $this->get('/vendor/manifest/nope/assets/core.js')->assertNotFound();
    }

    #[Test]
    public function it_publishes_prebuilt_files_to_the_same_url(): void
    {
        Artisan::call('vendor:publish', ['--tag' => 'manifest-dist']);

        $this->assertFileExists(public_path('vendor/manifest/widgets/assets/core-D1st0002.js'));
        $this->assertFileExists(public_path('vendor/manifest/widgets/.vite/manifest.json'));
    }
}
