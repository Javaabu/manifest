<?php

namespace Acme\Widgets;

use Illuminate\Support\ServiceProvider;
use Javaabu\Manifest\Concerns\RegistersAssets;
use Javaabu\Manifest\Package;

class WidgetsServiceProvider extends ServiceProvider
{
    use RegistersAssets;

    public function register(): void
    {
        $this->registerAssets('widgets', __DIR__ . '/../resources', function (Package $package) {
            $package
                ->js()
                ->style('theme', 'css/theme.scss')
                ->script('core', 'js/core.js', depends_on: ['theme'])
                ->vue('chart', 'js/chart.ts', depends_on: ['core'])
                ->npm(peer_dependencies: ['vue' => '^3.5']);
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'widgets');
    }
}
