<?php

namespace Javaabu\Manifest\Enums;

use Javaabu\Manifest\Exceptions\InvalidAssetException;

enum AssetTypes: string
{
    case CSS = 'css';
    case SASS = 'sass';
    case JS = 'js';
    case TS = 'ts';
    case REACT = 'react';
    case VUE = 'vue';

    public static function fromPath(string $path): self
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'css' => self::CSS,
            'scss', 'sass' => self::SASS,
            'js', 'mjs' => self::JS,
            'ts', 'mts' => self::TS,
            'jsx', 'tsx' => self::REACT,
            'vue' => self::VUE,
            default => throw InvalidAssetException::unsupportedExtension($path),
        };
    }

    public function isStyle(): bool
    {
        return match ($this) {
            self::CSS, self::SASS => true,
            default => false,
        };
    }

    public function isScript(): bool
    {
        return ! $this->isStyle();
    }

    /**
     * The npm packages an app needs to build an asset of this type.
     *
     * @return array<int, string>
     */
    public function buildRequirements(): array
    {
        return match ($this) {
            self::SASS => ['sass'],
            self::REACT => ['react', 'react-dom', '@vitejs/plugin-react'],
            self::VUE => ['vue', '@vitejs/plugin-vue'],
            default => [],
        };
    }
}
