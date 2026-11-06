<?php

namespace Javaabu\Manifest\Enums;

use Javaabu\Manifest\Exceptions\InvalidAssetException;

/**
 * What a package's npm dependencies are needed for, so manifest:check only
 * asks an app for what it builds.
 */
enum NpmScopes: string
{
    /** The registered assets, which the Vite plugin builds into every app. */
    case ASSETS = 'assets';

    /** Inertia pages and the JS apps import through the package's alias, built only when an app uses them. */
    case PAGES = 'pages';

    public static function fromValue(self|string $scope): self
    {
        if ($scope instanceof self) {
            return $scope;
        }

        return self::tryFrom($scope) ?? throw InvalidAssetException::invalidNpmScope($scope);
    }

    /**
     * The scope a dependency registered for the assets belongs to. Inertia's
     * adapters are only ever needed by pages.
     */
    public static function forDependency(string $name, self $scope): self
    {
        if ($scope === self::ASSETS && str_starts_with($name, '@inertiajs/')) {
            return self::PAGES;
        }

        return $scope;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
