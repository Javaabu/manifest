<?php

namespace Javaabu\Manifest\Exceptions;

use RuntimeException;

class CircularDependencyException extends RuntimeException
{
    /**
     * @param  array<int, string>  $chain
     */
    public static function chain(array $chain): self
    {
        $path = implode(' -> ', $chain);

        return new self("Circular asset dependency: {$path}.");
    }
}
