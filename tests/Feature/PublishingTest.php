<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PublishingTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(resource_path('vendor'));

        parent::tearDown();
    }

    #[Test]
    public function it_publishes_every_packages_assets_as_overrides(): void
    {
        Artisan::call('vendor:publish', ['--tag' => 'manifest-assets']);

        $this->assertFileExists(resource_path('vendor/filters/js/filter-bar.ts'));
        $this->assertFileExists(resource_path('vendor/filters/js/Pages/Index.tsx'));
        $this->assertFileExists(resource_path('vendor/filters/css/filter-bar.css'));
        $this->assertFileExists(resource_path('vendor/widgets/css/theme.scss'));
        $this->assertDirectoryDoesNotExist(resource_path('vendor/filters/views'));
    }

    #[Test]
    public function it_publishes_one_packages_assets(): void
    {
        Artisan::call('vendor:publish', ['--tag' => 'manifest-assets-widgets']);

        $this->assertFileExists(resource_path('vendor/widgets/js/core.js'));
        $this->assertDirectoryDoesNotExist(resource_path('vendor/filters'));
    }

    #[Test]
    public function it_overrides_one_file(): void
    {
        $this->artisan('manifest:override', ['target' => 'filters', 'path' => 'js/components/label.ts'])
            ->expectsOutputToContain('resources/vendor/filters/js/components/label.ts')
            ->assertSuccessful();

        $this->assertFileExists(resource_path('vendor/filters/js/components/label.ts'));
        $this->assertFileDoesNotExist(resource_path('vendor/filters/js/filter-bar.ts'));
    }

    #[Test]
    public function it_overrides_an_assets_entry(): void
    {
        $this->artisan('manifest:override', ['target' => 'filters::date-range'])->assertSuccessful();

        $this->assertFileExists(resource_path('vendor/filters/js/date-range.tsx'));
    }

    #[Test]
    public function it_keeps_an_existing_override_unless_forced(): void
    {
        File::ensureDirectoryExists(resource_path('vendor/filters/js'));
        File::put(resource_path('vendor/filters/js/filter-bar.ts'), 'mine');

        $this->artisan('manifest:override', ['target' => 'filters::filter-bar'])
            ->expectsOutputToContain('already exists')
            ->assertFailed();

        $this->assertEquals('mine', File::get(resource_path('vendor/filters/js/filter-bar.ts')));

        $this->artisan('manifest:override', ['target' => 'filters::filter-bar', '--force' => true])->assertSuccessful();

        $this->assertNotEquals('mine', File::get(resource_path('vendor/filters/js/filter-bar.ts')));
    }

    #[Test]
    public function it_explains_what_it_cannot_override(): void
    {
        $this->artisan('manifest:override', ['target' => 'filters::nope'])->assertFailed();
        $this->artisan('manifest:override', ['target' => 'nope', 'path' => 'js/a.ts'])->assertFailed();
        $this->artisan('manifest:override', ['target' => 'filters'])->expectsOutputToContain('Give the path')->assertFailed();
        $this->artisan('manifest:override', ['target' => 'filters', 'path' => 'js/missing.ts'])->expectsOutputToContain('There is no')->assertFailed();
    }
}
