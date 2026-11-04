<?php

namespace Javaabu\Manifest;

use Illuminate\Support\ServiceProvider;

class ManifestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/manifest.php', 'manifest');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'manifest');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/manifest.php' => config_path('manifest.php'),
            ], 'manifest-config');

            $this->publishes([
                __DIR__ . '/../lang' => $this->app->langPath('vendor/manifest'),
            ], 'manifest-translations');
        }
    }
}
