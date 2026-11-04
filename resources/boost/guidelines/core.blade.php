{{--
    javaabu/manifest guidelines for AI code assistants

    Maintained by: Javaabu (https://javaabu.com)
--}}

## javaabu/manifest

- Laravel packages in this project ship their CSS, JS, React and Vue through `javaabu/manifest`. Package assets are named `package::name`, are built by the app's Vite through the plugin in `vite.config.js`, and are injected into pages that use them. Don't add `<script>`/`<link>` tags or `laravel({ input })` entries for package assets, and don't copy package JS into the app.
- To change a package's frontend file, override that one file in `resources/vendor/{package}/` (`php artisan manifest:override`); never edit `vendor/`.
- Always activate the `javaabu-manifest` skill when working with package assets or Blade components that need JS/CSS, `vite.config.js`, Inertia pages named `package::Page`, `resources/vendor/*` overrides, or when writing a package that ships frontend code.
