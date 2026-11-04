<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TypesCommandTest extends TestCase
{
    protected string $tsconfig_backup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tsconfig_backup = File::get(base_path('tsconfig.json'));
    }

    protected function tearDown(): void
    {
        File::put(base_path('tsconfig.json'), $this->tsconfig_backup);
        File::delete(base_path('storage/framework/manifest.d.ts'));
        File::deleteDirectory(resource_path('vendor'));

        parent::tearDown();
    }

    #[Test]
    public function it_writes_types_for_every_package_page(): void
    {
        $this->artisan('manifest:types', ['--path' => 'storage/framework/manifest.d.ts', '--no-tsconfig' => true])
            ->assertSuccessful();

        $types = File::get(base_path('storage/framework/manifest.d.ts'));

        $this->assertStringContainsString('declare module "@javaabu/manifest/inertia" {', $types);
        $this->assertStringContainsString('"filters::Index": typeof import("../../vendor/acme/filters/resources/js/Pages/Index")', $types);
        $this->assertStringContainsString('"filters::Reports/Show": typeof import("../../vendor/acme/filters/resources/js/Pages/Reports/Show")', $types);
    }

    #[Test]
    public function it_points_overridden_pages_at_the_override(): void
    {
        File::ensureDirectoryExists(resource_path('vendor/filters/js/Pages'));
        File::put(resource_path('vendor/filters/js/Pages/Index.vue'), '<template />');

        $this->artisan('manifest:types', ['--path' => 'storage/framework/manifest.d.ts', '--no-tsconfig' => true]);

        $this->assertStringContainsString(
            '"filters::Index": typeof import("../../resources/vendor/filters/js/Pages/Index.vue")',
            File::get(base_path('storage/framework/manifest.d.ts')),
        );
    }

    #[Test]
    public function the_fixture_apps_committed_types_are_current(): void
    {
        $committed = File::get(resource_path('js/types/manifest.d.ts'));

        $this->artisan('manifest:types', ['--path' => 'resources/js/types/manifest.generated.d.ts', '--no-tsconfig' => true])
            ->assertSuccessful();

        $generated = File::get(resource_path('js/types/manifest.generated.d.ts'));

        File::delete(resource_path('js/types/manifest.generated.d.ts'));

        $this->assertEquals($committed, $generated, 'Run `vendor/bin/testbench manifest:types --no-tsconfig` and commit the result.');
    }

    #[Test]
    public function it_adds_import_paths_to_tsconfig_keeping_the_apps_own(): void
    {
        File::put(base_path('tsconfig.json'), json_encode([
            'compilerOptions' => ['strict' => true, 'paths' => ['@/*' => ['./resources/js/*']]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->artisan('manifest:types', ['--path' => 'storage/framework/manifest.d.ts'])
            ->expectsOutputToContain('Added the package import paths to tsconfig.json.')
            ->assertSuccessful();

        $tsconfig = json_decode(File::get(base_path('tsconfig.json')), true);

        $this->assertTrue($tsconfig['compilerOptions']['strict']);
        $this->assertEquals(['./resources/js/*'], $tsconfig['compilerOptions']['paths']['@/*']);
        $this->assertEquals(['./vendor/acme/filters/resources/js'], $tsconfig['compilerOptions']['paths']['@javaabu/filters']);
        $this->assertEquals(['./vendor/acme/filters/resources/js/*'], $tsconfig['compilerOptions']['paths']['@javaabu/filters/*']);
        $this->assertEquals(['./vendor/acme/widgets/resources/js'], $tsconfig['compilerOptions']['paths']['@javaabu/widgets']);
        $this->assertStringEndsWith('dist/inertia.d.ts', $tsconfig['compilerOptions']['paths']['@javaabu/manifest/inertia'][0]);
    }

    #[Test]
    public function it_keeps_two_space_indentation_and_respects_base_url(): void
    {
        File::put(base_path('tsconfig.json'), "{\n  \"compilerOptions\": {\n    \"baseUrl\": \"resources\"\n  }\n}\n");

        $this->artisan('manifest:types', ['--path' => 'storage/framework/manifest.d.ts'])->assertSuccessful();

        $contents = File::get(base_path('tsconfig.json'));

        $this->assertStringContainsString("\n  \"compilerOptions\": {\n    \"baseUrl\"", $contents);
        $this->assertEquals(['../vendor/acme/filters/resources/js'], json_decode($contents, true)['compilerOptions']['paths']['@javaabu/filters']);
    }

    #[Test]
    public function it_leaves_a_tsconfig_with_comments_alone_and_prints_the_paths(): void
    {
        $original = "{\n  // The app's settings\n  \"compilerOptions\": {}\n}\n";

        File::put(base_path('tsconfig.json'), $original);

        Artisan::call('manifest:types', ['--path' => 'storage/framework/manifest.d.ts']);

        $output = Artisan::output();

        $this->assertStringContainsString('has comments', $output);
        $this->assertStringContainsString('"@javaabu/filters/*"', $output);
        $this->assertEquals($original, File::get(base_path('tsconfig.json')));
    }

    #[Test]
    public function it_prints_the_paths_without_a_tsconfig(): void
    {
        File::delete(base_path('tsconfig.json'));

        Artisan::call('manifest:types', ['--path' => 'storage/framework/manifest.d.ts']);

        $this->assertStringContainsString('There is no tsconfig.json', Artisan::output());
    }
}
