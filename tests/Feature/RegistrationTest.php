<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Javaabu\Manifest\Asset;
use Javaabu\Manifest\Concerns\RegistersAssets;
use Javaabu\Manifest\Exceptions\CircularDependencyException;
use Javaabu\Manifest\Exceptions\UnknownAssetException;
use Javaabu\Manifest\Facades\Manifest;
use Javaabu\Manifest\Manifest as ManifestRegistry;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RegistrationTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(resource_path('vendor'));

        parent::tearDown();
    }

    #[Test]
    public function it_registers_assets_from_package_service_providers(): void
    {
        $this->assertTrue(Manifest::hasPackage('widgets'));
        $this->assertTrue(Manifest::hasPackage('filters'));
        $this->assertTrue(Manifest::has('filters::date-range'));
        $this->assertFalse(Manifest::has('filters::missing'));
        $this->assertFalse(Manifest::has('missing::bar'));

        $this->assertSame(app(ManifestRegistry::class), app(ManifestRegistry::class));
    }

    #[Test]
    public function it_lets_a_package_add_to_its_registration(): void
    {
        Manifest::package('filters')->script('extra', 'js/extra.ts');

        $this->assertTrue(Manifest::has('filters::extra'));
    }

    #[Test]
    public function it_resolves_assets_in_dependency_order(): void
    {
        $ids = $this->ids(Manifest::resolve(['filters::date-range']));

        $this->assertEquals([
            'widgets::theme',
            'widgets::core',
            'filters::styles',
            'filters::filter-bar',
            'filters::date-range',
        ], $ids);
    }

    #[Test]
    public function it_resolves_each_asset_once(): void
    {
        $ids = $this->ids(Manifest::resolve([
            'widgets::core',
            'filters::date-range',
            'filters::filter-bar',
            'widgets::theme',
        ]));

        $this->assertEquals([
            'widgets::theme',
            'widgets::core',
            'filters::styles',
            'filters::filter-bar',
            'filters::date-range',
        ], $ids);
    }

    #[Test]
    public function it_resolves_a_whole_package(): void
    {
        $ids = $this->ids(Manifest::resolve(['widgets']));

        $this->assertEquals(['widgets::theme', 'widgets::core', 'widgets::chart'], $ids);
    }

    #[Test]
    public function it_lists_every_asset_in_dependency_order(): void
    {
        $ids = $this->ids(Manifest::all());

        $this->assertCount(6, $ids);
        $this->assertLessThan(array_search('filters::filter-bar', $ids), array_search('widgets::core', $ids));
        $this->assertLessThan(array_search('filters::filter-bar', $ids), array_search('filters::styles', $ids));
    }

    #[Test]
    public function it_fails_on_an_unknown_asset(): void
    {
        $this->expectException(UnknownAssetException::class);
        $this->expectExceptionMessage('Asset [filters::missing] is not registered.');

        Manifest::resolve(['filters::missing']);
    }

    #[Test]
    public function it_names_the_asset_that_needs_an_unknown_dependency(): void
    {
        Manifest::package('filters')->script('broken', 'js/broken.ts', depends_on: ['forms::select']);

        $this->expectException(UnknownAssetException::class);
        $this->expectExceptionMessage('It is a dependency of [filters::broken].');

        Manifest::resolve(['filters::broken']);
    }

    #[Test]
    public function it_fails_on_an_unknown_package(): void
    {
        $this->expectException(UnknownAssetException::class);
        $this->expectExceptionMessage('No package named [forms]');

        Manifest::resolve(['forms']);
    }

    #[Test]
    public function it_fails_on_circular_dependencies(): void
    {
        Manifest::package('loop', '/loop')
            ->script('a', 'a.js', depends_on: ['b'])
            ->script('b', 'b.js', depends_on: ['c'])
            ->script('c', 'c.js', depends_on: ['a']);

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage('loop::a -> loop::b -> loop::c -> loop::a');

        Manifest::resolve(['loop::a']);
    }

    #[Test]
    public function it_resolves_entries_to_the_package_or_an_override(): void
    {
        $asset = Manifest::find('filters::filter-bar');

        $this->assertEquals(
            $this->packagePath('filters/resources/js/filter-bar.ts'),
            Manifest::entryPath($asset),
        );

        File::ensureDirectoryExists(resource_path('vendor/filters/js'));
        File::put(resource_path('vendor/filters/js/filter-bar.ts'), 'export {}');

        $this->assertEquals(resource_path('vendor/filters/js/filter-bar.ts'), Manifest::entryPath($asset));
        $this->assertEquals(
            $this->packagePath('filters/resources/js/date-range.tsx'),
            Manifest::entryPath(Manifest::find('filters::date-range')),
        );
    }

    #[Test]
    public function it_reads_the_overrides_path_from_config(): void
    {
        config(['manifest.overrides_path' => '/app/overrides/']);

        $this->assertEquals('/app/overrides/filters/js/a.ts', Manifest::overridePath('filters', '/js/a.ts'));
    }

    #[Test]
    public function it_registers_lazily_when_the_manifest_resolves_late(): void
    {
        $this->app->forgetInstance(ManifestRegistry::class);

        $provider = new class($this->app) extends ServiceProvider
        {
            use RegistersAssets;

            public function register(): void
            {
                $this->registerAssets('late', '/late', fn ($package) => $package->script('app', 'app.js'));
            }
        };

        $provider->register();

        $this->assertTrue(Manifest::has('late::app'));
    }

    /**
     * @param  array<int, Asset>  $assets
     * @return array<int, string>
     */
    protected function ids(array $assets): array
    {
        return array_map(fn (Asset $asset) => $asset->id(), $assets);
    }
}
