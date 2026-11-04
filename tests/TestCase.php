<?php

namespace Javaabu\Manifest\Tests;

use Javaabu\Manifest\ManifestServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ManifestServiceProvider::class,
        ];
    }
}
