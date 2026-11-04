<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Javaabu\Manifest\Facades\Manifest;
use Javaabu\Manifest\Testing\ManifestFake;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;

class FakeTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('/auto', fn () => view('auto'));
        $router->get('/plain', fn () => view('plain'));
    }

    #[Test]
    public function it_records_requirements_and_injection_without_a_vite_build(): void
    {
        $fake = Manifest::fake();

        $this->assertInstanceOf(ManifestFake::class, $fake);

        $this->get('/auto')
            ->assertOk()
            ->assertSee('<script type="module" src="/vendor/acme/filters/resources/js/filter-bar.ts" data-manifest-asset="filters::filter-bar"></script>', false)
            ->assertSee('<link rel="stylesheet" href="/vendor/acme/widgets/resources/css/theme.scss" data-manifest-asset="widgets::theme" />', false);

        Manifest::assertAssetRequired('filters::filter-bar');
        Manifest::assertAssetRequired('widgets::core');
        Manifest::assertAssetInjected('widgets::chart');
        Manifest::assertAssetNotRequired('filters::date-range');
        Manifest::assertAssetNotInjected('filters::date-range');

        $this->assertEquals(
            ['widgets::theme', 'widgets::core', 'filters::styles', 'filters::filter-bar', 'widgets::chart'],
            $fake->requiredAssets(),
        );
    }

    #[Test]
    public function it_keeps_the_registered_packages(): void
    {
        Manifest::fake();

        $this->assertTrue(Manifest::has('filters::date-range'));
        $this->assertSame(Manifest::getFacadeRoot(), app(\Javaabu\Manifest\Manifest::class));
    }

    #[Test]
    public function it_asserts_nothing_was_required(): void
    {
        Manifest::fake();

        $this->get('/plain');

        Manifest::assertNothingRequired();
    }

    #[Test]
    public function it_records_components_rendered_outside_a_request(): void
    {
        Manifest::fake();

        Blade::render('<x-filters::date-range />');

        Manifest::assertAssetRequired('filters::date-range');
        Manifest::assertAssetNotInjected('filters::date-range');
    }

    #[Test]
    public function its_assertions_fail_with_what_happened(): void
    {
        Manifest::fake();

        Manifest::require('widgets::core');

        try {
            Manifest::assertAssetRequired('filters::filter-bar');
        } catch (AssertionFailedError $error) {
            $this->assertStringContainsString('Asset [filters::filter-bar] was not required. Required: [widgets::theme, widgets::core].', $error->getMessage());

            return;
        }

        $this->fail('The assertion passed.');
    }

    #[Test]
    public function its_negative_assertions_fail_too(): void
    {
        Manifest::fake();

        Manifest::require('widgets::core');

        $this->expectException(AssertionFailedError::class);

        Manifest::assertNothingRequired();
    }
}
