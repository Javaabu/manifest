<?php

namespace Javaabu\Manifest\Rendering;

use Javaabu\Manifest\Asset;
use Javaabu\Manifest\AssetRequirements;
use Javaabu\Manifest\Manifest;

/**
 * Fills the directives' placeholders and injects whatever is left: styles
 * before </head>, scripts before </body>, each asset once, in dependency order.
 */
class Injector
{
    public const string PLACEHOLDER_PATTERN = '/<!--\[manifest:(styles|scripts):([^\]]*)\]-->/';

    public function __construct(
        protected Manifest $manifest,
        protected AssetRequirements $requirements,
        protected TagRenderer $renderer,
    ) {}

    public static function placeholder(string $kind, string $scope = '*'): string
    {
        return "<!--[manifest:{$kind}:{$scope}]-->";
    }

    public function hasWork(string $html): bool
    {
        return ! $this->requirements->isEmpty()
            || str_contains($html, '<!--[manifest:');
    }

    public function inject(string $html, bool $auto = true): string
    {
        $ordered = $this->manifest->resolve($this->requirements->ids());

        $html = $this->fillPlaceholders($html, $ordered);

        if (! $auto) {
            return $html;
        }

        $styles = $this->take($ordered, 'styles', fn (Asset $asset) => true);
        $scripts = $this->take($ordered, 'scripts', fn (Asset $asset) => true);

        if ($styles) {
            $html = $this->insertBefore($html, '</head>', $this->renderer->render($styles)->toHtml(), last: false);
        }

        if ($scripts) {
            $html = $this->insertBefore($html, '</body>', $this->renderer->render($scripts)->toHtml(), last: true);
        }

        return $html;
    }

    /**
     * @param  array<int, Asset>  $ordered
     */
    protected function fillPlaceholders(string $html, array $ordered): string
    {
        if (! preg_match_all(self::PLACEHOLDER_PATTERN, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        // Placeholders for a package or an asset go first, so a catch-all
        // placeholder only gets what the others didn't claim.
        $order = array_keys($matches);

        usort($order, fn (int $a, int $b) => (($matches[$a][2][0] === '*') <=> ($matches[$b][2][0] === '*')) ?: ($a <=> $b));

        $contents = [];

        foreach ($order as $index) {
            $kind = $matches[$index][1][0];
            $scope = $matches[$index][2][0];

            $assets = $this->take($ordered, $kind, $this->scopeFilter($scope, $ordered));

            $contents[$index] = $this->renderer->render($assets)->toHtml();
        }

        foreach (array_reverse(array_keys($matches)) as $index) {
            [$placeholder, $offset] = $matches[$index][0];

            $html = substr_replace($html, $contents[$index], $offset, strlen($placeholder));
        }

        return $html;
    }

    /**
     * A placeholder scoped to packages or assets gets the required assets of
     * those packages, the named assets, and what they depend on.
     *
     * @param  array<int, Asset>  $ordered
     * @return callable(Asset): bool
     */
    protected function scopeFilter(string $scope, array $ordered): callable
    {
        if ($scope === '*') {
            return fn (Asset $asset) => true;
        }

        $items = array_filter(explode(',', $scope));

        $ids = array_filter($items, fn (string $item) => str_contains($item, '::'));
        $packages = array_flip(array_diff($items, $ids));

        foreach ($ordered as $asset) {
            if (isset($packages[$asset->package])) {
                $ids[] = $asset->id();
            }
        }

        $in_scope = array_flip(array_map(fn (Asset $asset) => $asset->id(), $this->manifest->resolve(array_values($ids))));

        return fn (Asset $asset) => isset($in_scope[$asset->id()]);
    }

    /**
     * The not-yet-printed assets of a kind that pass the filter, marked as
     * printed.
     *
     * @param  array<int, Asset>  $ordered
     * @param  callable(Asset): bool  $filter
     * @return array<int, Asset>
     */
    protected function take(array $ordered, string $kind, callable $filter): array
    {
        $taken = [];

        foreach ($ordered as $asset) {
            if ($this->requirements->wasPrinted($asset->id())) {
                continue;
            }

            if ($kind === 'styles' && ! $asset->isStyle()) {
                continue;
            }

            if ($kind === 'scripts' && ! $asset->isScript()) {
                continue;
            }

            if (! $filter($asset)) {
                continue;
            }

            $this->requirements->markPrinted($asset->id());

            $taken[] = $asset;
        }

        return $taken;
    }

    protected function insertBefore(string $html, string $tag, string $content, bool $last): string
    {
        $position = $last
            ? strripos($html, $tag)
            : stripos($html, $tag);

        if ($position === false) {
            return $html;
        }

        return substr_replace($html, $content, $position, 0);
    }
}
