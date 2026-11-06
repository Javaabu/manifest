<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Javaabu\Manifest\Manifest;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CheckCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        File::delete(base_path('package.json'));
        File::delete(base_path('pnpm-lock.yaml'));
        File::deleteDirectory(base_path('node_modules'));

        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $dependencies
     * @param  array<string, string>  $installed
     */
    protected function app(array $dependencies, array $installed): void
    {
        File::put(base_path('package.json'), json_encode(['devDependencies' => $dependencies]));

        foreach ($installed as $name => $version) {
            File::ensureDirectoryExists(base_path("node_modules/{$name}"));
            File::put(base_path("node_modules/{$name}/package.json"), json_encode(['name' => $name, 'version' => $version]));
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, array<string, mixed>>
     */
    protected function check(array $options = []): array
    {
        Artisan::call('manifest:check', ['--json' => true, ...$options]);

        return collect(json_decode(Artisan::output(), true))->keyBy('name')->all();
    }

    #[Test]
    public function it_passes_when_everything_is_installed(): void
    {
        $installed = [
            '@javaabu/onu' => '1.4.0',
            '@vitejs/plugin-react' => '6.1.2',
            '@vitejs/plugin-vue' => '6.0.10',
            'laravel-vite-plugin' => '3.2.0',
            'react' => '19.3.1',
            'react-dom' => '19.3.1',
            'sass' => '1.107.1',
            'vite' => '8.3.5',
            'vue' => '3.5.44',
        ];

        $this->app(array_map(fn ($version) => "^{$version}", $installed), $installed);

        $this->artisan('manifest:check')
            ->expectsOutputToContain('Everything the packages need is installed.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_reports_what_is_missing_uninstalled_or_the_wrong_version(): void
    {
        $this->app(
            ['react' => '^18.0', 'react-dom' => '^19.0', 'vite' => '^8.0', '@javaabu/onu' => 'github:Javaabu/onu#main'],
            ['react' => '18.3.1', 'vite' => '8.3.5', '@javaabu/onu' => '0.9.0'],
        );

        $rows = $this->check();

        $this->assertEquals('mismatch', $rows['react']['status']);
        $this->assertEquals(['^19.0'], $rows['react']['constraints']);
        $this->assertEquals('not_installed', $rows['react-dom']['status']);
        $this->assertEquals('ok', $rows['vite']['status']);
        $this->assertEquals(['widgets', 'filters'], $rows['vite']['needed_by']);
        $this->assertEquals('missing', $rows['vue']['status']);
        $this->assertEquals('missing', $rows['sass']['status']);
        $this->assertEquals('mismatch', $rows['@javaabu/onu']['status']);

        $this->artisan('manifest:check')->assertFailed();
    }

    #[Test]
    public function it_prints_the_install_command_for_the_apps_package_manager(): void
    {
        $this->app([], []);

        File::put(base_path('pnpm-lock.yaml'), '');

        Artisan::call('manifest:check');

        $output = Artisan::output();

        $this->assertStringContainsString('pnpm add -D @javaabu/onu@"^1.0"', $output);
        $this->assertStringContainsString('react@"^19.0"', $output);
        $this->assertStringContainsString(' sass ', $output);
    }

    #[Test]
    public function it_can_not_check_versions_that_are_not_ranges(): void
    {
        app(Manifest::class)->package('filters')->npm(['@javaabu/forms' => 'github:Javaabu/react-forms#main']);

        $this->app(['@javaabu/forms' => 'github:Javaabu/react-forms#main'], ['@javaabu/forms' => '0.0.0']);

        $this->assertEquals('unchecked', $this->check()['@javaabu/forms']['status']);

        Artisan::call('manifest:check');

        $this->assertStringContainsString('@javaabu/forms', Artisan::output());
    }

    #[Test]
    public function it_does_not_ask_blade_apps_for_what_only_package_pages_need(): void
    {
        $installed = [
            '@javaabu/onu' => '1.4.0',
            '@vitejs/plugin-react' => '6.1.2',
            '@vitejs/plugin-vue' => '6.0.10',
            'laravel-vite-plugin' => '3.2.0',
            'react' => '19.3.1',
            'react-dom' => '19.3.1',
            'sass' => '1.107.1',
            'vite' => '8.3.5',
            'vue' => '3.5.44',
        ];

        $this->app(array_map(fn ($version) => "^{$version}", $installed), $installed);

        $rows = $this->check();

        $this->assertArrayNotHasKey('recharts', $rows);
        $this->assertArrayNotHasKey('@inertiajs/react', $rows);
        $this->assertEquals(['assets'], $rows['react']['scopes']);

        Artisan::call('manifest:check');

        $output = Artisan::output();

        $this->assertStringContainsString("Package pages weren't checked", $output);
        $this->assertStringContainsString('Everything the packages need is installed.', $output);
        $this->assertStringNotContainsString('recharts', $output);
    }

    #[Test]
    public function it_checks_what_package_pages_need_when_the_app_uses_inertia(): void
    {
        $this->app(['@inertiajs/react' => '^3.0'], ['@inertiajs/react' => '3.2.0']);

        $rows = $this->check();

        $this->assertEquals('ok', $rows['@inertiajs/react']['status']);
        $this->assertEquals(['pages'], $rows['@inertiajs/react']['scopes']);
        $this->assertEquals('missing', $rows['recharts']['status']);
        $this->assertEquals(['filters'], $rows['recharts']['needed_by']);
        $this->assertEquals(['assets'], $rows['sass']['scopes']);

        Artisan::call('manifest:check');

        $this->assertStringNotContainsString("Package pages weren't checked", Artisan::output());
    }

    #[Test]
    public function it_checks_the_scopes_the_app_asks_for(): void
    {
        $this->app([], []);

        $pages = $this->check(['--scope' => ['pages']]);

        $this->assertEquals(['@inertiajs/react', 'laravel-vite-plugin', 'recharts', 'vite'], array_keys($pages));
        // Both packages' JS can be imported through their aliases
        $this->assertEquals(['widgets', 'filters'], $pages['vite']['needed_by']);

        $both = $this->check(['--scope' => ['assets', 'pages']]);

        $this->assertArrayHasKey('recharts', $both);
        $this->assertArrayHasKey('sass', $both);
    }

    #[Test]
    public function it_rejects_unknown_scopes(): void
    {
        $this->app([], []);

        $this->artisan('manifest:check', ['--scope' => ['inertia']])
            ->expectsOutputToContain('Unknown scope [inertia]. Use assets, pages.')
            ->assertFailed();
    }

    #[Test]
    public function it_checks_in_dhivehi(): void
    {
        app()->setLocale('dv');

        $this->app([], []);

        Artisan::call('manifest:check');

        $output = Artisan::output();

        $this->assertStringContainsString('package.json ގައި ނެތް', $output);
        $this->assertStringContainsString('npm ޕެކޭޖު ނެތް ނުވަތަ ވާޝަން ރަނގަޅެއް ނޫން', $output);
        $this->assertStringContainsString('ޕެކޭޖުތަކުގެ ޕޭޖުތައް ޗެކެއް ނުކުރެވުނު', $output);
    }
}
