<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ListCommandTest extends TestCase
{
    #[Test]
    public function it_lists_assets_with_their_paths_and_dependency_order(): void
    {
        $this->artisan('manifest:list', ['package' => 'filters'])
            ->expectsTable(['Asset', 'Type', 'Entry', 'Depends on'], [
                ['widgets::theme', 'sass', 'vendor/acme/widgets/resources/css/theme.scss', ''],
                ['widgets::core', 'js', 'vendor/acme/widgets/resources/js/core.js', 'widgets::theme'],
                ['filters::styles', 'css', 'vendor/acme/filters/resources/css/filter-bar.css', 'widgets::theme'],
                ['filters::filter-bar', 'ts', 'vendor/acme/filters/resources/js/filter-bar.ts', 'widgets::core, filters::styles'],
                ['filters::date-range', 'react', 'vendor/acme/filters/resources/js/date-range.tsx', 'filters::filter-bar'],
            ])
            ->expectsOutputToContain('5 assets, in dependency order.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_lists_assets_as_json(): void
    {
        $this->assertEquals(0, Artisan::call('manifest:list', ['--json' => true]));

        $assets = json_decode(Artisan::output(), true);

        $this->assertCount(6, $assets);
        $this->assertEquals('widgets::chart', $assets[2]['id']);
        $this->assertEquals('vue', $assets[2]['type']);
        $this->assertEquals(['widgets::core'], $assets[2]['dependencies']);
        $this->assertFalse($assets[2]['overridden']);
        $this->assertStringEndsWith('widgets/resources/js/chart.ts', $assets[2]['path']);
    }

    #[Test]
    public function it_lists_in_dhivehi(): void
    {
        app()->setLocale('dv');

        Artisan::call('manifest:list', ['package' => 'widgets']);

        $output = Artisan::output();

        $this->assertStringContainsString('ބަރޯސާވަނީ', $output);
        $this->assertStringContainsString('3 އެސެޓް، ބަރޯސާވާ ތަރުތީބުން.', $output);
    }
}
