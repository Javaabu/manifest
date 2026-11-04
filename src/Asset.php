<?php

namespace Javaabu\Manifest;

use Javaabu\Manifest\Enums\AssetTypes;

final readonly class Asset
{
    /**
     * @param  array<int, string>  $dependencies  fully qualified ids (package::name)
     * @param  array<string, string|bool>  $attributes  extra HTML attributes for the tag
     */
    public function __construct(
        public string $package,
        public string $name,
        public string $entry,
        public AssetTypes $type,
        public array $dependencies = [],
        public array $attributes = [],
    ) {}

    public function id(): string
    {
        return self::makeId($this->package, $this->name);
    }

    public function isStyle(): bool
    {
        return $this->type->isStyle();
    }

    public function isScript(): bool
    {
        return $this->type->isScript();
    }

    public static function makeId(string $package, string $name): string
    {
        return "{$package}::{$name}";
    }

    /**
     * Splits an id into its package and name. A bare name belongs to the
     * given default package.
     *
     * @return array{0: ?string, 1: string}
     */
    public static function parseId(string $id, ?string $default_package = null): array
    {
        if (! str_contains($id, '::')) {
            return [$default_package, $id];
        }

        [$package, $name] = explode('::', $id, 2);

        return [$package, $name];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'package' => $this->package,
            'name' => $this->name,
            'type' => $this->type->value,
            'entry' => $this->entry,
            'dependencies' => $this->dependencies,
            'attributes' => $this->attributes,
        ];
    }
}
