<?php

namespace Javaabu\Manifest\Testing;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Vite;
use Javaabu\Manifest\Asset;
use Javaabu\Manifest\Manifest;
use Javaabu\Manifest\Rendering\TagRenderer;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Manifest::fake(): keeps the registered packages, records what's required
 * and injected across requests, and prints predictable tags instead of
 * asking Vite, so package tests need no build.
 */
class ManifestFake extends Manifest
{
    /** @var array<string, true> */
    protected array $required_ids = [];

    /** @var array<string, true> */
    protected array $injected_ids = [];

    public static function swap(Application $app): static
    {
        $real = $app->make(Manifest::class);

        $fake = new static($app);
        $fake->packages = $real instanceof Manifest ? $real->packages : [];

        $app->instance(Manifest::class, $fake);
        $app->bind(TagRenderer::class, fn ($app) => new FakeTagRenderer($fake, $app->make(Vite::class)));

        return $fake;
    }

    public function require(string|array ...$ids): static
    {
        parent::require(...$ids);

        $ids = array_merge(...array_map(fn ($id) => (array) $id, $ids));

        foreach ($this->resolve($ids) as $asset) {
            $this->required_ids[$asset->id()] = true;
        }

        return $this;
    }

    /**
     * @param  array<int, Asset>  $assets
     */
    public function recordInjected(array $assets): void
    {
        foreach ($assets as $asset) {
            $this->injected_ids[$asset->id()] = true;
        }
    }

    /**
     * Every asset required so far, with dependencies.
     *
     * @return array<int, string>
     */
    public function requiredAssets(): array
    {
        return array_keys($this->required_ids);
    }

    /**
     * Every asset whose tags were printed so far.
     *
     * @return array<int, string>
     */
    public function injectedAssets(): array
    {
        return array_keys($this->injected_ids);
    }

    public function assertAssetRequired(string $id): void
    {
        PHPUnit::assertArrayHasKey($id, $this->required_ids, "Asset [{$id}] was not required. Required: [" . implode(', ', $this->requiredAssets()) . '].');
    }

    public function assertAssetNotRequired(string $id): void
    {
        PHPUnit::assertArrayNotHasKey($id, $this->required_ids, "Asset [{$id}] was required.");
    }

    public function assertNothingRequired(): void
    {
        PHPUnit::assertEmpty($this->required_ids, 'Assets were required: [' . implode(', ', $this->requiredAssets()) . '].');
    }

    public function assertAssetInjected(string $id): void
    {
        PHPUnit::assertArrayHasKey($id, $this->injected_ids, "Asset [{$id}] was not injected into a page. Injected: [" . implode(', ', $this->injectedAssets()) . '].');
    }

    public function assertAssetNotInjected(string $id): void
    {
        PHPUnit::assertArrayNotHasKey($id, $this->injected_ids, "Asset [{$id}] was injected into a page.");
    }
}
