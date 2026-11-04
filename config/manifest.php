<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic injection
    |--------------------------------------------------------------------------
    |
    | When enabled, the styles and scripts required while rendering a page are
    | injected into HTML responses: styles before </head>, scripts before
    | </body>. Assets placed with the Blade directives are never injected
    | a second time.
    |
    */

    'inject' => true,

    /*
    |--------------------------------------------------------------------------
    | Overrides
    |--------------------------------------------------------------------------
    |
    | Apps override a package's resources file by file: a file at
    | {overrides_path}/{package}/js/FilterBar.tsx is used instead of the
    | package's resources/js/FilterBar.tsx. Defaults to resources/vendor.
    |
    */

    'overrides_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Discovery file
    |--------------------------------------------------------------------------
    |
    | php artisan manifest:discover writes the registered packages and assets
    | here for the Vite plugin. It runs again after package:discover (so on
    | composer install and dump-autoload) and when Vite starts. Defaults to
    | bootstrap/cache/manifest.json.
    |
    */

    'discovery_path' => null,

];
