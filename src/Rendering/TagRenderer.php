<?php

namespace Javaabu\Manifest\Rendering;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Javaabu\Manifest\Asset;
use Javaabu\Manifest\Manifest;

/**
 * Renders asset tags with the app's own Vite instance, so hot mode, the build
 * manifest, preloading, integrity and the CSP nonce work as they do for @vite.
 * Assets the app's build doesn't have come from the package's prebuilt dist.
 */
class TagRenderer
{
    /** @var array<string, array<string, mixed>|null> */
    protected array $manifests = [];

    public function __construct(
        protected Manifest $manifest,
        protected Vite $vite,
    ) {}

    /**
     * @param  array<int, Asset>  $assets
     */
    public function render(array $assets): HtmlString
    {
        $html = '';
        $batch = [];
        $batch_key = null;

        foreach ($assets as $asset) {
            [$key, $entry] = $this->source($asset);

            if ($batch && $key !== $batch_key) {
                $html .= $this->renderBatch($batch_key, $batch);
                $batch = [];
            }

            $batch_key = $key;
            $batch[] = [$asset, $entry];
        }

        if ($batch) {
            $html .= $this->renderBatch($batch_key, $batch);
        }

        return new HtmlString($html);
    }

    /**
     * The asset's Vite input: its entry, with overrides applied, relative to
     * the app's root.
     */
    public function entry(Asset $asset): string
    {
        return $this->manifest->relativeToBase($this->manifest->entryPath($asset));
    }

    /**
     * Whether the asset is served from its package's prebuilt dist.
     */
    public function usesDist(Asset $asset): bool
    {
        return $this->source($asset)[0] !== 'app';
    }

    /**
     * Where an asset's tags come from ('app', or 'dist:{package}') and its
     * entry there.
     *
     * @return array{0: string, 1: string}
     */
    protected function source(Asset $asset): array
    {
        $app = ['app', $this->entry($asset)];
        $mode = config('manifest.dist', 'auto');

        if ($mode === 'never' || $this->manifest->isOverridden($asset)) {
            return $app;
        }

        $dist_entry = $this->distEntry($asset);

        if (! $dist_entry) {
            return $app;
        }

        $dist = ["dist:{$asset->package}", $dist_entry];

        if ($mode === 'always') {
            return $dist;
        }

        if ($this->vite->isRunningHot()) {
            return $app;
        }

        $app_manifest = $this->readManifest($this->appManifestPath());

        return $app_manifest !== null && isset($app_manifest[$app[1]])
            ? $app
            : $dist;
    }

    /**
     * The key of the asset's entry in its package's dist manifest: the entry
     * itself when the package was built from its resources folder (as the
     * build preset does), or resources/{entry} when built from its root.
     */
    protected function distEntry(Asset $asset): ?string
    {
        $dist = $this->manifest->distPath($asset->package);

        $manifest_path = $dist ? DistVite::manifestIn($dist) : null;

        if (! $manifest_path) {
            return null;
        }

        $manifest = $this->readManifest($manifest_path) ?? [];

        $resources = basename($this->manifest->package($asset->package)->path());

        foreach ([$asset->entry, "{$resources}/{$asset->entry}"] as $key) {
            if (isset($manifest[$key])) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{0: Asset, 1: string}>  $batch
     */
    protected function renderBatch(string $key, array $batch): string
    {
        $vite = $key === 'app'
            ? clone $this->vite
            : $this->distVite(substr($key, strlen('dist:')));

        $entries = [];
        $attributes = [];

        foreach ($batch as [$asset, $entry]) {
            $entries[] = $entry;

            if ($asset->attributes) {
                $attributes[$entry] = $asset->attributes;
            }
        }

        if ($attributes) {
            $resolver = fn (string $src) => $attributes[$src] ?? [];

            $vite->useScriptTagAttributes($resolver);
            $vite->useStyleTagAttributes($resolver);
        }

        return $vite($entries)->toHtml();
    }

    protected function distVite(string $package): DistVite
    {
        $manifest_path = DistVite::manifestIn((string) $this->manifest->distPath($package));

        return DistVite::forPackage($this->vite, (string) $manifest_path, $this->manifest->distUrlPath($package));
    }

    protected function appManifestPath(): string
    {
        return (fn () => $this->manifestPath($this->buildDirectory))->call($this->vite);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readManifest(string $path): ?array
    {
        if (array_key_exists($path, $this->manifests)) {
            return $this->manifests[$path];
        }

        $data = is_file($path)
            ? json_decode((string) file_get_contents($path), true)
            : null;

        return $this->manifests[$path] = is_array($data) ? $data : null;
    }
}
