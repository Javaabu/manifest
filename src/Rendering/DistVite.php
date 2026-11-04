<?php

namespace Javaabu\Manifest\Rendering;

use Illuminate\Foundation\Vite;

/**
 * Renders a package's prebuilt assets from its own Vite manifest. It copies
 * the app's Vite settings (CSP nonce, integrity, tag attributes, asset URL
 * resolver), so the tags match what @vite would print.
 */
class DistVite extends Vite
{
    protected string $dist_manifest = '';

    public static function forPackage(Vite $vite, string $manifest_path, string $url_path): static
    {
        $instance = new static;

        $instance->copyFrom($vite);

        $instance->dist_manifest = $manifest_path;
        $instance->buildDirectory = trim($url_path, '/');
        $instance->hotFile = '';

        return $instance;
    }

    /**
     * The manifest of a package's dist folder, in either place Vite writes it.
     */
    public static function manifestIn(string $dist_path): ?string
    {
        foreach (['.vite/manifest.json', 'manifest.json'] as $file) {
            if (is_file("{$dist_path}/{$file}")) {
                return "{$dist_path}/{$file}";
            }
        }

        return null;
    }

    public function isRunningHot()
    {
        return false;
    }

    protected function manifestPath($buildDirectory)
    {
        return $this->dist_manifest;
    }

    protected function copyFrom(Vite $vite): void
    {
        foreach (get_object_vars($vite) as $property => $value) {
            $this->{$property} = $value;
        }
    }
}
