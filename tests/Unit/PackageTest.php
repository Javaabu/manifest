<?php

namespace Javaabu\Manifest\Tests\Unit;

use Javaabu\Manifest\Asset;
use Javaabu\Manifest\Enums\AssetTypes;
use Javaabu\Manifest\Enums\NpmScopes;
use Javaabu\Manifest\Exceptions\InvalidAssetException;
use Javaabu\Manifest\Package;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PackageTest extends TestCase
{
    #[Test]
    public function it_registers_each_asset_type(): void
    {
        $package = (new Package('filters', '/packages/filters/resources/'))
            ->style('base', 'css/base.css')
            ->style('theme', 'css/theme.scss')
            ->script('plain', 'js/plain.js')
            ->script('typed', 'js/typed.ts')
            ->react('island', 'js/island.tsx')
            ->vue('widget', 'js/widget.ts');

        $types = array_map(fn (Asset $asset) => $asset->type, $package->assets());

        $this->assertEquals([
            'base' => AssetTypes::CSS,
            'theme' => AssetTypes::SASS,
            'plain' => AssetTypes::JS,
            'typed' => AssetTypes::TS,
            'island' => AssetTypes::REACT,
            'widget' => AssetTypes::VUE,
        ], $types);

        $this->assertEquals('/packages/filters/resources', $package->path());
        $this->assertEquals('/packages/filters/resources/css/base.css', $package->path('/css/base.css'));
    }

    #[Test]
    public function it_registers_several_assets_at_once(): void
    {
        $package = (new Package('filters', '/filters'))
            ->styles(['base' => 'css/base.css'])
            ->scripts([
                'bar' => 'js/bar.ts',
                'range' => ['js/range.ts', ['bar', 'forms::select']],
            ]);

        $this->assertEquals(['filters::bar', 'forms::select'], $package->asset('range')->dependencies);
        $this->assertEquals([], $package->asset('bar')->dependencies);
        $this->assertTrue($package->asset('base')->isStyle());
    }

    #[Test]
    public function it_qualifies_dependencies_with_the_package_name(): void
    {
        $asset = (new Package('filters', '/filters'))
            ->script('bar', 'js/bar.ts', depends_on: ['core', 'forms::select', 'core'])
            ->asset('bar');

        $this->assertEquals('filters::bar', $asset->id());
        $this->assertEquals(['filters::core', 'forms::select'], $asset->dependencies);
    }

    #[Test]
    public function it_keeps_html_attributes_for_the_tag(): void
    {
        $asset = (new Package('filters', '/filters'))
            ->script('bar', 'js/bar.ts', attributes: ['data-turbo-track' => 'reload'])
            ->asset('bar');

        $this->assertEquals(['data-turbo-track' => 'reload'], $asset->attributes);
    }

    #[Test]
    public function it_rejects_a_script_registered_as_a_style(): void
    {
        $this->expectException(InvalidAssetException::class);
        $this->expectExceptionMessage('[filters::bar] is registered as a style');

        (new Package('filters', '/filters'))->style('bar', 'js/bar.ts');
    }

    #[Test]
    public function it_rejects_a_stylesheet_registered_as_a_script(): void
    {
        $this->expectException(InvalidAssetException::class);

        (new Package('filters', '/filters'))->react('bar', 'css/bar.css');
    }

    #[Test]
    public function it_rejects_duplicate_names(): void
    {
        $this->expectException(InvalidAssetException::class);
        $this->expectExceptionMessage('[filters::bar] is already registered');

        (new Package('filters', '/filters'))
            ->script('bar', 'js/bar.ts')
            ->style('bar', 'css/bar.css');
    }

    #[Test]
    public function it_rejects_invalid_names(): void
    {
        $this->expectException(InvalidAssetException::class);

        (new Package('filters', '/filters'))->script('forms::bar', 'js/bar.ts');
    }

    #[Test]
    public function it_rejects_invalid_package_names(): void
    {
        $this->expectException(InvalidAssetException::class);

        new Package('Acme/Filters', '/filters');
    }

    #[Test]
    public function it_only_has_an_import_alias_when_it_ships_js(): void
    {
        $package = new Package('filters', '/filters');

        $this->assertNull($package->importAlias());

        $package->js();

        $this->assertEquals('js', $package->jsPath());
        $this->assertEquals('@javaabu/filters', $package->importAlias());

        $package->js('src/js/', '@acme/filters');

        $this->assertEquals('src/js', $package->jsPath());
        $this->assertEquals('@acme/filters', $package->importAlias());
    }

    #[Test]
    public function it_defaults_the_js_folder_when_only_an_alias_is_given(): void
    {
        $package = (new Package('forms', '/forms'))->alias('@javaabu/forms-blade');

        $this->assertEquals('js', $package->jsPath());
        $this->assertEquals('@javaabu/forms-blade', $package->importAlias());
    }

    #[Test]
    public function it_records_pages_dist_and_npm_dependencies(): void
    {
        $package = (new Package('filters', '/filters'))
            ->pages()
            ->dist()
            ->npm(['@javaabu/onu' => '^1.0'], ['react' => '^19.0'])
            ->npm(['dayjs' => '^1.11']);

        $this->assertEquals('js/Pages', $package->pagesPath());
        $this->assertEquals('../dist', $package->distPath());
        $this->assertEquals(['@javaabu/onu' => '^1.0', 'dayjs' => '^1.11'], $package->npmDependencies());
        $this->assertEquals(['react' => '^19.0'], $package->npmPeerDependencies());
    }

    #[Test]
    public function it_scopes_npm_dependencies_to_assets_or_pages(): void
    {
        $package = (new Package('filters', '/filters'))
            ->npm(['@javaabu/onu' => '^1.0'], ['react' => '^19.0', '@inertiajs/react' => '^3.0'])
            ->npm(['recharts' => '^3.0'], for: NpmScopes::PAGES)
            ->npm(['uqr' => '^0.1'], for: 'pages');

        $this->assertEquals(['@javaabu/onu' => '^1.0'], $package->npmDependencies(NpmScopes::ASSETS));
        $this->assertEquals(['react' => '^19.0'], $package->npmPeerDependencies(NpmScopes::ASSETS));
        $this->assertEquals(['recharts' => '^3.0', 'uqr' => '^0.1'], $package->npmDependencies(NpmScopes::PAGES));
        $this->assertEquals(['@inertiajs/react' => '^3.0'], $package->npmPeerDependencies(NpmScopes::PAGES));
        $this->assertEquals(['@javaabu/onu' => '^1.0', 'recharts' => '^3.0', 'uqr' => '^0.1'], $package->npmDependencies());
    }

    #[Test]
    public function it_rejects_unknown_npm_scopes(): void
    {
        $this->expectException(InvalidAssetException::class);
        $this->expectExceptionMessage('Invalid npm scope [inertia]. Use assets or pages.');

        (new Package('filters', '/filters'))->npm(['recharts' => '^3.0'], for: 'inertia');
    }

    #[Test]
    public function it_registers_vite_plugins_with_their_options(): void
    {
        $package = (new Package('localization', '/localization/resources'))
            ->vitePlugin('../dist/vite.js', ['watch' => ['lang']])
            ->vitePlugin('../dist/hmr.mjs')
            ->vitePlugin('/../dist/vite.js', ['watch' => ['lang', 'config']]);

        $this->assertEquals([
            ['module' => '../dist/hmr.mjs', 'options' => []],
            ['module' => '../dist/vite.js', 'options' => ['watch' => ['lang', 'config']]],
        ], $package->vitePlugins());
    }

    #[Test]
    public function it_only_registers_built_javascript_as_a_vite_plugin(): void
    {
        $this->expectException(InvalidAssetException::class);
        $this->expectExceptionMessage('Invalid Vite plugin [js/vite.ts] for package [localization]. Register built JavaScript (.js or .mjs)');

        (new Package('localization', '/localization/resources'))->vitePlugin('js/vite.ts');
    }

    #[Test]
    public function it_only_accepts_vite_plugin_options_that_can_be_written_as_json(): void
    {
        $this->expectException(InvalidAssetException::class);
        $this->expectExceptionMessage("The options of Vite plugin [../dist/vite.js] for package [localization] can't be written as JSON.");

        (new Package('localization', '/localization/resources'))->vitePlugin('../dist/vite.js', ['bad' => NAN]);
    }

    #[Test]
    public function it_parses_asset_ids(): void
    {
        $this->assertEquals(['forms', 'select'], Asset::parseId('forms::select'));
        $this->assertEquals(['filters', 'bar'], Asset::parseId('bar', 'filters'));
        $this->assertEquals([null, 'bar'], Asset::parseId('bar'));
    }
}
