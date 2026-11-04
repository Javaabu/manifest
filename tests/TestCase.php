<?php

namespace Javaabu\Manifest\Tests;

use Acme\Filters\FiltersServiceProvider;
use Acme\Widgets\WidgetsServiceProvider;
use Javaabu\Manifest\ManifestServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Whether to load the fixture packages in tests/Fixtures/packages.
     */
    protected bool $with_fixture_packages = true;

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

    protected function fixturePath(string $path = ''): string
    {
        return rtrim(__DIR__ . '/Fixtures/' . ltrim($path, '/'), '/');
    }
}
