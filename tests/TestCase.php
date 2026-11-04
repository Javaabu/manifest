<?php

namespace Javaabu\Manifest\Tests;

use Acme\Filters\FiltersServiceProvider;
use Acme\Widgets\WidgetsServiceProvider;
use Illuminate\Support\Facades\File;
use Javaabu\Manifest\ManifestServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Whether to load the fixture packages in tests/Fixtures/packages.
     */
    protected bool $with_fixture_packages = true;

    protected static bool $cleared_compiled_views = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Compiled views outlive changes to the Blade hooks in src/.
        if (! static::$cleared_compiled_views) {
            File::cleanDirectory(storage_path('framework/views'));
            File::put(storage_path('framework/views/.gitignore'), "*\n!.gitignore\n");

            static::$cleared_compiled_views = true;
        }
    }

    /**
     * A small Laravel app in tests/Fixtures/app, with the fixture packages
     * installed in its vendor folder like real Composer packages.
     */
    public static function applicationBasePath(): string
    {
        return __DIR__ . '/Fixtures/app';
    }

    protected function getPackageProviders($app): array
    {
        if (! $this->with_fixture_packages) {
            return [ManifestServiceProvider::class];
        }

        return [
            WidgetsServiceProvider::class,
            ManifestServiceProvider::class,
            FiltersServiceProvider::class,
        ];
    }

    protected function packagePath(string $path = ''): string
    {
        return rtrim(base_path('vendor/acme/' . ltrim($path, '/')), '/');
    }
}
