<?php

namespace Javaabu\Manifest\Testing;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Javaabu\Manifest\Asset;
use Javaabu\Manifest\Rendering\TagRenderer;

/**
 * Prints a tag per asset, with data-manifest-asset="package::name", without
 * a Vite build or dev server.
 */
class FakeTagRenderer extends TagRenderer
{
    public function __construct(
        protected ManifestFake $fake,
        Vite $vite,
    ) {
        parent::__construct($fake, $vite);
    }

    public function render(array $assets): HtmlString
    {
        $this->fake->recordInjected($assets);

        $html = array_map(function (Asset $asset) {
            $url = e('/' . $this->entry($asset));
            $id = e($asset->id());

            return $asset->isStyle()
                ? "<link rel=\"stylesheet\" href=\"{$url}\" data-manifest-asset=\"{$id}\" />"
                : "<script type=\"module\" src=\"{$url}\" data-manifest-asset=\"{$id}\"></script>";
        }, $assets);

        return new HtmlString(implode('', $html));
    }
}
