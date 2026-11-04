<?php

namespace Javaabu\Manifest\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\HtmlString;
use Javaabu\Manifest\Asset;
use Javaabu\Manifest\Manifest as ManifestRegistry;
use Javaabu\Manifest\Package;
use Javaabu\Manifest\Testing\ManifestFake;

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
 * @method static ManifestRegistry require(string|array ...$ids)
 * @method static bool isRequired(string $id)
 * @method static array<int, Asset> required()
 * @method static array<string, string> pages()
 * @method static HtmlString renderStyles(array $scope = [])
 * @method static HtmlString renderScripts(array $scope = [])
 * @method static void assertAssetRequired(string $id)
 * @method static void assertAssetNotRequired(string $id)
 * @method static void assertNothingRequired()
 * @method static void assertAssetInjected(string $id)
 * @method static void assertAssetNotInjected(string $id)
 *
 * @see ManifestRegistry
 */
class Manifest extends Facade
{
    /**
     * Record what's required and injected, and print predictable tags
     * (data-manifest-asset="package::name") instead of asking Vite.
     */
    public static function fake(): ManifestFake
    {
        $fake = ManifestFake::swap(static::getFacadeApplication());

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return ManifestRegistry::class;
    }
}
