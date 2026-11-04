<?php

namespace Acme\Filters;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Javaabu\Manifest\Concerns\RegistersAssets;
use Javaabu\Manifest\Package;

class FiltersServiceProvider extends ServiceProvider
{
    use RegistersAssets;

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filters');

        Blade::componentNamespace('Acme\\Filters\\Components', 'filters');

        $this->registerAssets('filters', __DIR__ . '/../resources', function (Package $package) {
            $package
                ->js()
                ->pages()
                ->script('filter-bar', 'js/filter-bar.ts', depends_on: ['widgets::core', 'styles'])
                ->style('styles', 'css/filter-bar.css', depends_on: ['widgets::theme'])
                ->react('date-range', 'js/date-range.tsx', depends_on: ['filter-bar'])
                ->npm(['@javaabu/onu' => '^1.0'], ['react' => '^19.0', 'react-dom' => '^19.0']);
        });
    }
}
