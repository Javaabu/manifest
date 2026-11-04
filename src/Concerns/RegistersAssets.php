<?php

namespace Javaabu\Manifest\Concerns;

use Closure;
use Illuminate\Support\ServiceProvider;
use Javaabu\Manifest\Manifest;
use Javaabu\Manifest\Package;
use WeakMap;

/**
 * For package service providers. Works from register() or boot(), whatever
 * order the providers load in.
 *
 * @mixin ServiceProvider
 */
trait RegistersAssets
{
    /**
     * @param  string  $name  the package name assets are namespaced by (name::asset)
     * @param  string  $path  the package's resources folder
     * @param  Closure(Package): mixed  $callback
     */
    protected function registerAssets(string $name, string $path, Closure $callback): void
    {
        $registered = new WeakMap;

        $this->callAfterResolving(Manifest::class, function (Manifest $manifest) use ($name, $path, $callback, $registered) {
            if (isset($registered[$manifest])) {
                return;
            }

            $registered[$manifest] = true;

            $callback($manifest->package($name, $path));
        });
    }
}
