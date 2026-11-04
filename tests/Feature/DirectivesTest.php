<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Javaabu\Manifest\Facades\Manifest;
use Javaabu\Manifest\Tests\Support\InteractsWithVite;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DirectivesTest extends TestCase
{
    use InteractsWithVite;

    protected function defineRoutes($router): void
    {
        $router->get('/directives', fn () => view('directives'));
        $router->get('/named', fn () => view('named'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->useViteBuild();
    }

    #[Test]
    public function it_places_styles_where_the_directive_is_even_for_components_rendered_later(): void
    {
        $html = $this->get('/directives')->assertOk()->getContent();

        [$head] = explode('</head>', $html, 2);

        $this->assertStringContainsString('widgets-theme-abc123.css', $head);
        $this->assertStringContainsString('filters-styles-abc123.css', $head);
        $this->assertStringNotContainsString('<!--[manifest:', $html);
    }

    #[Test]
    public function it_places_a_package_scope_first_and_the_rest_at_the_catch_all(): void
    {
        $html = $this->get('/directives')->getContent();

        [, $main] = explode('<main>', $html, 2);
        [$main, $after_main] = explode('</main>', $main, 2);

        $this->assertStringContainsString('src="http://localhost/build/assets/widgets-core-abc123.js"', $main);
        $this->assertStringContainsString('src="http://localhost/build/assets/widgets-chart-abc123.js"', $main);
        $this->assertStringNotContainsString('filters-filter-bar', $main);

        $this->assertStringContainsString('src="http://localhost/build/assets/filters-filter-bar-abc123.js"', $after_main);
        $this->assertStringNotContainsString('src="http://localhost/build/assets/widgets-core-abc123.js"', $after_main);
    }

    #[Test]
    public function it_does_not_inject_again_what_a_directive_printed(): void
    {
        $html = $this->get('/directives')->getContent();

        $this->assertEquals(1, substr_count($html, 'rel="stylesheet" href="http://localhost/build/assets/widgets-theme-abc123.css"'));
        $this->assertEquals(1, substr_count($html, 'src="http://localhost/build/assets/widgets-core-abc123.js"'));
        $this->assertEquals(1, substr_count($html, 'src="http://localhost/build/assets/filters-filter-bar-abc123.js"'));
    }

    #[Test]
    public function it_fills_directives_when_auto_injection_is_off(): void
    {
        config(['manifest.inject' => false]);

        $html = $this->get('/directives')->getContent();

        $this->assertStringContainsString('filters-filter-bar-abc123.js', $html);
        $this->assertStringContainsString('widgets-theme-abc123.css', $html);
    }

    #[Test]
    public function it_requires_assets_named_in_a_directive_with_their_dependencies(): void
    {
        $html = $this->get('/named')->assertOk()->getContent();

        [$head, $body] = explode('</head>', $html, 2);

        $this->assertStringContainsString('widgets-theme-abc123.css', $head);
        $this->assertStringContainsString('filters-styles-abc123.css', $head);
        $this->assertStringContainsString('src="http://localhost/build/assets/widgets-core-abc123.js"', $body);
        $this->assertStringNotContainsString('filters-filter-bar', $html);
    }

    #[Test]
    public function it_fails_while_rendering_for_an_unknown_asset_or_package(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('No package named [nope]');

        Blade::render("@manifestScripts('nope')");
    }

    #[Test]
    public function it_requires_assets_with_the_require_directive(): void
    {
        Blade::render("@manifestRequire('filters::date-range', ['widgets::chart'])");

        $this->assertTrue(Manifest::isRequired('filters::date-range'));
        $this->assertTrue(Manifest::isRequired('filters::filter-bar'));
        $this->assertTrue(Manifest::isRequired('widgets::chart'));
    }

    #[Test]
    public function it_renders_tags_immediately_outside_a_request(): void
    {
        Manifest::require('filters::filter-bar');

        $styles = Manifest::renderStyles()->toHtml();
        $scripts = Manifest::renderScripts(['widgets'])->toHtml();
        $rest = Manifest::renderScripts()->toHtml();

        $this->assertStringContainsString('widgets-theme-abc123.css', $styles);
        $this->assertStringContainsString('filters-styles-abc123.css', $styles);
        $this->assertStringContainsString('widgets-core-abc123.js', $scripts);
        $this->assertStringNotContainsString('filters-filter-bar', $scripts);
        $this->assertStringContainsString('filters-filter-bar-abc123.js', $rest);
        $this->assertStringNotContainsString('widgets-core', $rest);
        $this->assertEquals('', Manifest::renderScripts()->toHtml());
    }
}
