<?php

namespace Javaabu\Manifest\Tests\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Javaabu\Manifest\Asset;
use Javaabu\Manifest\Manifest;
use Javaabu\Manifest\Rendering\TagRenderer;
use ReflectionProperty;

trait InteractsWithVite
{
    protected function tearDownInteractsWithVite(): void
    {
        File::delete(public_path('hot'));
        File::deleteDirectory(public_path('build'));
        File::deleteDirectory(resource_path('vendor'));

        $this->forgetViteManifests();
    }

    /**
     * Vite caches manifests in a static property, across tests.
     */
    protected function forgetViteManifests(): void
    {
        (new ReflectionProperty(Vite::class, 'manifests'))->setValue(null, []);
    }

    protected function useViteDevServer(string $url = 'http://localhost:5173'): void
    {
        File::put(public_path('hot'), $url);
    }

    /**
     * Writes public/build/manifest.json as `vite build` would for every
     * registered asset: name-hash.js / name-hash.css.
     */
    protected function useViteBuild(): void
    {
        $renderer = app(TagRenderer::class);
        $entries = [];

        foreach (app(Manifest::class)->all() as $asset) {
            $entry = $renderer->entry($asset);

            $entries[$entry] = [
                'file' => $this->builtFile($asset),
                'src' => $entry,
                'isEntry' => true,
            ];
        }

        File::ensureDirectoryExists(public_path('build'));
        File::put(public_path('build/manifest.json'), json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->forgetViteManifests();
    }

    protected function builtFile(Asset $asset): string
    {
        $name = str_replace(['::', '/'], '-', $asset->id());

        return $asset->isStyle()
            ? "assets/{$name}-abc123.css"
            : "assets/{$name}-abc123.js";
    }
}
