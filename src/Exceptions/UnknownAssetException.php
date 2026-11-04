<?php

namespace Javaabu\Manifest\Exceptions;

use RuntimeException;

class UnknownAssetException extends RuntimeException
{
    public static function asset(string $id, ?string $required_by = null): self
    {
        $message = "Asset [{$id}] is not registered.";

        if ($required_by) {
            $message .= " It is a dependency of [{$required_by}].";
        }

        return new self($message . ' Check that its package is installed and registers it with javaabu/manifest.');
    }

    public static function package(string $package): self
    {
        return new self("No package named [{$package}] has registered assets with javaabu/manifest.");
    }
}
