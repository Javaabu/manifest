<?php

namespace Javaabu\Manifest;

/**
 * What the current request needs, and what has been printed. Bound as a
 * scoped instance, so it resets between requests and Octane workers.
 */
class AssetRequirements
{
    /** @var array<string, true> */
    protected array $required = [];

    /** @var array<string, true> */
    protected array $printed = [];

    public function add(string $id): void
    {
        $this->required[$id] = true;
    }

    /** @return array<int, string> */
    public function ids(): array
    {
        return array_keys($this->required);
    }

    public function has(string $id): bool
    {
        return isset($this->required[$id]);
    }

    public function isEmpty(): bool
    {
        return ! $this->required;
    }

    public function markPrinted(string $id): void
    {
        $this->printed[$id] = true;
    }

    public function wasPrinted(string $id): bool
    {
        return isset($this->printed[$id]);
    }

    /** @return array<int, string> */
    public function printed(): array
    {
        return array_keys($this->printed);
    }

    public function flush(): void
    {
        $this->required = [];
        $this->printed = [];
    }
}
