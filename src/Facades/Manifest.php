<?php

namespace Javaabu\Manifest\Facades;

use Illuminate\Support\Facades\Facade;
use Javaabu\Manifest\Asset;
use Javaabu\Manifest\Package;

/**
 * @method static Package package(string $name, ?string $path = null)
 * @method static bool hasPackage(string $name)
 * @method static array<string, Package> packages()
 * @method static bool has(string $id)
 * @method static Asset find(string $id, ?string $required_by = null)
 * @method static array<int, Asset> all()
 * @method static array<int, Asset> resolve(array $ids)
 * @method static string overridePath(string $package, ?string $relative = null)
 * @method static string resolvePath(string $package, string $relative)
 * @method static string entryPath(Asset $asset)
 *
 * @see \Javaabu\Manifest\Manifest
 */
class Manifest extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Javaabu\Manifest\Manifest::class;
    }
}
