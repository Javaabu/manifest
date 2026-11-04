<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Javaabu\Manifest\Exceptions\UnknownAssetException;
use Javaabu\Manifest\Facades\Manifest;
use Javaabu\Manifest\Tests\Support\InteractsWithVite;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InjectionTest extends TestCase
{
    use InteractsWithVite;

    protected function defineRoutes($router): void
    {
        $router->get('/auto', fn () => view('auto'));
        $router->get('/plain', fn () => view('plain'));
        $router->get('/fragment', fn () => view('fragment'));
        $router->get('/json', function () {
            Manifest::require('filters::filter-bar');

            return response()->json(['html' => '<html><head></head><body></body></html>']);
        });
        $router->get('/download', function () {
            Manifest::require('filters::filter-bar');

            return response()->streamDownload(fn () => print ('<html><head></head><body></body></html>'), 'page.html');
        });
        $router->get('/text', function () {
            Manifest::require('filters::filter-bar');

            return response('<html><head></head><body></body></html>', 200, ['Content-Type' => 'text/plain']);
        });
        $router->get('/inertia', function () {
            Manifest::require('filters::filter-bar');

            return response('<html><head></head><body></body></html>', 200, ['X-Inertia' => 'true']);
        });
        $router->get('/redirect', function () {
            Manifest::require('filters::filter-bar');

            return redirect('/plain');
        });
        $router->get('/unknown', function () {
            Manifest::require('filters::missing');

            return view('plain');
        });
    }

    #[Test]
    public function it_injects_styles_into_the_head_and_scripts_into_the_body(): void
    {
        $this->useViteBuild();

        $html = $this->get('/auto')->assertOk()->getContent();

        [$head, $body] = explode('</head>', $html, 2);

        $this->assertStringContainsString('href="http://localhost/build/assets/widgets-theme-abc123.css"', $head);
        $this->assertStringContainsString('href="http://localhost/build/assets/filters-styles-abc123.css"', $head);
        $this->assertStringNotContainsString('<script', $head);

        $this->assertStringContainsString('src="http://localhost/build/assets/widgets-core-abc123.js"', $body);
        $this->assertStringContainsString('src="http://localhost/build/assets/filters-filter-bar-abc123.js"', $body);
        $this->assertStringContainsString('src="http://localhost/build/assets/widgets-chart-abc123.js"', $body);
        $this->assertStringNotContainsString('rel="stylesheet"', $body);
    }

    #[Test]
    public function it_injects_each_asset_once_in_dependency_order(): void
    {
        $this->useViteBuild();

        $html = $this->get('/auto')->getContent();

        $this->assertEquals(1, substr_count($html, 'src="http://localhost/build/assets/filters-filter-bar-abc123.js"'));
        $this->assertEquals(1, substr_count($html, 'src="http://localhost/build/assets/widgets-core-abc123.js"'));
        $this->assertEquals(1, substr_count($html, 'rel="stylesheet" href="http://localhost/build/assets/widgets-theme-abc123.css"'));

        $this->assertInOrder($html, [
            'widgets-theme-abc123.css',
            'filters-styles-abc123.css',
            '</head>',
            'data-filter-bar',
            'src="http://localhost/build/assets/widgets-core-abc123.js"',
            'src="http://localhost/build/assets/filters-filter-bar-abc123.js"',
            'src="http://localhost/build/assets/widgets-chart-abc123.js"',
            '</body>',
        ]);
    }

    #[Test]
    public function it_injects_dev_server_tags_when_vite_is_running_hot(): void
    {
        $this->useViteDevServer();

        $html = $this->get('/auto')->getContent();

        $this->assertStringContainsString('<link rel="stylesheet" href="http://localhost:5173/vendor/acme/widgets/resources/css/theme.scss" />', $html);
        $this->assertStringContainsString('<script type="module" src="http://localhost:5173/@vite/client"></script>', $html);
        $this->assertStringContainsString('<script type="module" src="http://localhost:5173/vendor/acme/filters/resources/js/filter-bar.ts"></script>', $html);
    }

    #[Test]
    public function it_uses_the_csp_nonce(): void
    {
        $this->useViteBuild();

        Vite::useCspNonce('ނޮންސް-123');

        $html = $this->get('/auto')->getContent();

        $this->assertStringContainsString('nonce="ނޮންސް-123"', $html);
        $this->assertStringNotContainsString('<script type="module" src="http://localhost/build/assets/widgets-core-abc123.js"></script>', $html);
    }

    #[Test]
    public function it_adds_the_attributes_an_asset_declares(): void
    {
        $this->useViteBuild();

        Manifest::package('filters')->script('tracked', 'js/filter-bar.ts', attributes: ['data-turbo-track' => 'reload']);

        Route::get('/tracked', function () {
            Manifest::require('filters::tracked');

            return view('plain');
        });

        $this->useViteDevServer();

        $html = $this->get('/tracked')->getContent();

        $this->assertStringContainsString('data-turbo-track="reload"', $html);
    }

    #[Test]
    public function it_leaves_pages_without_requirements_alone(): void
    {
        $this->useViteBuild();

        $this->get('/plain')->assertOk()->assertContent(view('plain')->render());
    }

    #[Test]
    public function it_leaves_fragments_without_head_or_body_alone(): void
    {
        $this->useViteBuild();

        $html = $this->get('/fragment')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<link', $html);
    }

    #[Test]
    public function it_never_injects_into_json_downloads_text_inertia_or_redirects(): void
    {
        $this->useViteBuild();

        $this->assertStringNotContainsString('abc123', $this->get('/json')->assertOk()->getContent());
        $this->assertStringNotContainsString('abc123', $this->get('/download')->assertOk()->streamedContent());
        $this->assertStringNotContainsString('abc123', $this->get('/text')->assertOk()->getContent());
        $this->assertStringNotContainsString('abc123', $this->get('/inertia')->assertOk()->getContent());
        $this->assertStringNotContainsString('abc123', $this->get('/redirect')->assertRedirect('/plain')->getContent());
        $this->assertStringNotContainsString('abc123', $this->get('/auto', ['X-Inertia' => 'true'])->getContent());
    }

    #[Test]
    public function it_can_be_turned_off(): void
    {
        $this->useViteBuild();

        config(['manifest.inject' => false]);

        $html = $this->get('/auto')->assertOk()->getContent();

        $this->assertStringNotContainsString('abc123', $html);
    }

    #[Test]
    public function it_starts_each_request_with_no_requirements(): void
    {
        $this->useViteBuild();

        $this->get('/auto');

        $html = $this->get('/plain')->getContent();

        $this->assertStringNotContainsString('abc123', $html);
    }

    #[Test]
    public function it_shows_an_error_page_for_an_unknown_asset(): void
    {
        $this->get('/unknown')->assertInternalServerError();

        $this->withoutExceptionHandling();

        $this->expectException(UnknownAssetException::class);

        $this->get('/unknown');
    }

    #[Test]
    public function it_shows_an_error_page_when_the_vite_manifest_is_missing(): void
    {
        $this->get('/auto')->assertInternalServerError();
    }

    /**
     * @param  array<int, string>  $needles
     */
    protected function assertInOrder(string $haystack, array $needles): void
    {
        $offset = 0;

        foreach ($needles as $needle) {
            $position = strpos($haystack, $needle, $offset);

            $this->assertNotFalse($position, "[{$needle}] is missing or out of order.");

            $offset = $position + strlen($needle);
        }
    }
}
