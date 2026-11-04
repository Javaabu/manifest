<?php

namespace Javaabu\Manifest\Exceptions;

use InvalidArgumentException;

class InvalidAssetException extends InvalidArgumentException
{
    public static function unsupportedExtension(string $path): self
    {
        return new self("Unsupported asset type for [{$path}]. Use a .css, .scss, .sass, .js, .ts, .jsx, .tsx or .vue file.");
    }

    public static function invalidName(string $name): self
    {
        return new self("Invalid asset name [{$name}]. Use letters, numbers, dashes, dots and slashes, without [::].");
    }

    public static function notAStyle(string $id, string $entry): self
    {
        return new self("Asset [{$id}] is registered as a style, but [{$entry}] is a script.");
    }

    public static function notAScript(string $id, string $entry): self
    {
        return new self("Asset [{$id}] is registered as a script, but [{$entry}] is a stylesheet.");
    }

    public static function duplicate(string $id): self
    {
        return new self("Asset [{$id}] is already registered. Asset names must be unique within a package.");
    }

    public static function invalidPackageName(string $name): self
    {
        return new self("Invalid package name [{$name}]. Use lowercase letters, numbers and dashes.");
    }
}
