<?php

namespace Javaabu\Manifest\Rendering;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Javaabu\Manifest\Asset;
use Javaabu\Manifest\Manifest;

/**
 * Renders asset tags with the app's own Vite instance, so hot mode, the build
 * manifest, preloading, integrity and the CSP nonce work as they do for @vite.
 */
class TagRenderer
{
    public function __construct(
        protected Manifest $manifest,
        protected Vite $vite,
    ) {}

    /**
     * @param  array<int, Asset>  $assets
     */
    public function render(array $assets): HtmlString
    {
        if (! $assets) {
            return new HtmlString('');
        }

        $entries = [];
        $attributes = [];

        foreach ($assets as $asset) {
            $entry = $this->entry($asset);

            $entries[] = $entry;

            if ($asset->attributes) {
                $attributes[$entry] = $asset->attributes;
            }
        }

        $vite = clone $this->vite;

        if ($attributes) {
            $resolver = fn (string $src) => $attributes[$src] ?? [];

            $vite->useScriptTagAttributes($resolver);
            $vite->useStyleTagAttributes($resolver);
        }

        return $vite($entries);
    }

    /**
     * The asset's Vite input: its entry, with overrides applied, relative to
     * the app's root.
     */
    public function entry(Asset $asset): string
    {
        return $this->manifest->relativeToBase($this->manifest->entryPath($asset));
    }
}
