<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Javaabu\Manifest\Facades\Manifest;
use Javaabu\Manifest\Tests\Support\InteractsWithVite;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class DiscoverCommandTest extends TestCase
{
    use InteractsWithVite;

    protected function tearDown(): void
    {
        File::delete(base_path('bootstrap/cache/manifest.json'));
        File::deleteDirectory(base_path('vendor/composer'));

        if (is_link(base_path('vendor/acme/linked'))) {
            unlink(base_path('vendor/acme/linked'));
        }

        parent::tearDown();
    }

    #[Test]
    public function it_writes_packages_and_assets_for_the_vite_plugin(): void
    {
        $this->artisan('manifest:discover')
            ->expectsOutputToContain('Wrote 2 packages to bootstrap/cache/manifest.json.')
            ->assertSuccessful();

        $data = json_decode(File::get(base_path('bootstrap/cache/manifest.json')), true);

        $this->assertEquals(1, $data['version']);

        $this->assertEquals([
            'path' => 'vendor/acme/filters/resources',
            'alias' => '@javaabu/filters',
            'js' => 'js',
            'pages' => 'js/Pages',
            'dist' => null,
            'overrides' => 'resources/vendor/filters',
            'dependencies' => ['@javaabu/onu' => '^1.0', 'recharts' => '^3.0'],
            'peerDependencies' => ['react' => '^19.0', 'react-dom' => '^19.0', '@inertiajs/react' => '^2.0 || ^3.0'],
        ], $data['packages']['filters']);

        $this->assertEquals(
            ['widgets::theme', 'widgets::core', 'widgets::chart', 'filters::styles', 'filters::filter-bar', 'filters::date-range'],
            array_column($data['assets'], 'id'),
        );

        $this->assertEquals([
            'id' => 'filters::date-range',
            'package' => 'filters',
            'name' => 'date-range',
            'type' => 'react',
            'entry' => 'js/date-range.tsx',
            'input' => 'vendor/acme/filters/resources/js/date-range.tsx',
            'dependencies' => ['filters::filter-bar'],
        ], $data['assets'][5]);
    }

    #[Test]
    public function it_uses_the_override_as_the_input(): void
    {
        File::ensureDirectoryExists(resource_path('vendor/filters/js'));
        File::put(resource_path('vendor/filters/js/date-range.tsx'), 'export {}');

        $this->artisan('manifest:discover')->assertSuccessful();

        $data = json_decode(File::get(base_path('bootstrap/cache/manifest.json')), true);

        $this->assertEquals('resources/vendor/filters/js/date-range.tsx', $data['assets'][5]['input']);
    }

    #[Test]
    public function it_writes_to_the_configured_path(): void
    {
        config(['manifest.discovery_path' => storage_path('framework/manifest-test.json')]);

        $this->artisan('manifest:discover')->assertSuccessful();

        $this->assertFileExists(storage_path('framework/manifest-test.json'));

        File::delete(storage_path('framework/manifest-test.json'));
    }

    #[Test]
    public function it_maps_path_repository_packages_back_to_vendor(): void
    {
        $outside = sys_get_temp_dir() . '/manifest-linked-' . uniqid();

        File::ensureDirectoryExists("{$outside}/resources/js");
        File::put("{$outside}/resources/js/app.ts", 'export {}');

        symlink($outside, base_path('vendor/acme/linked'));

        File::ensureDirectoryExists(base_path('vendor/composer'));
        File::put(base_path('vendor/composer/installed.json'), json_encode([
            'packages' => [
                ['name' => 'acme/linked', 'dist' => ['type' => 'path', 'url' => '../linked']],
                ['name' => 'acme/filters', 'dist' => ['type' => 'zip']],
            ],
        ]));

        // Like __DIR__ in a symlinked package: the real path.
        Manifest::package('linked', realpath($outside) . '/resources')->script('app', 'js/app.ts');

        $this->artisan('manifest:discover')->assertSuccessful();

        $data = json_decode(File::get(base_path('bootstrap/cache/manifest.json')), true);

        $this->assertEquals('vendor/acme/linked/resources', $data['packages']['linked']['path']);
        $this->assertContains('vendor/acme/linked/resources/js/app.ts', array_column($data['assets'], 'input'));

        File::deleteDirectory($outside);
    }

    #[Test]
    public function it_runs_again_in_a_fresh_process_after_package_discovery(): void
    {
        Process::fake();

        Event::dispatch(new CommandFinished('package:discover', new ArrayInput([]), new NullOutput, 0));
        Event::dispatch(new CommandFinished('package:discover', new ArrayInput([]), new NullOutput, 1));
        Event::dispatch(new CommandFinished('migrate', new ArrayInput([]), new NullOutput, 0));

        Process::assertRanTimes(function (PendingProcess $process) {
            return $process->command === [PHP_BINARY, 'artisan', 'manifest:discover']
                && $process->path === base_path();
        }, 1);
    }
}
