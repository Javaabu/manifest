<?php

namespace Javaabu\Manifest\Tests\Feature;

use Illuminate\Support\Facades\Vite;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Renders pages against the real build of the fixture app, made by
 * `pnpm test:e2e` (tests/e2e/vite.test.ts). Skipped until that has run.
 */
#[Group('e2e')]
class ViteBuildTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(public_path('e2e-build/manifest.json'))) {
            $this->markTestSkipped('Run `pnpm build && pnpm test:e2e` first to build the fixture app.');
        }

        Vite::useBuildDirectory('e2e-build');
        Vite::useHotFile(public_path('e2e-hot-unused'));
    }

    protected function defineRoutes($router): void
    {
        $router->get('/auto', fn () => view('auto'));
    }

    #[Test]
    public function it_renders_tags_for_files_the_vite_build_produced(): void
    {
        $html = $this->get('/auto')->assertOk()->getContent();

        preg_match_all('#(?:src|href)="http://localhost/(e2e-build/assets/[^"]+)"#', $html, $matches);

        $files = array_unique($matches[1]);

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertFileExists(public_path($file));
        }

        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="http://localhost/e2e-build/assets/theme-[^"]+\.css"#', $html);
        $this->assertMatchesRegularExpression('#<script type="module" src="http://localhost/e2e-build/assets/filter-bar-[^"]+\.js"#', $html);
        $this->assertMatchesRegularExpression('#<script type="module" src="http://localhost/e2e-build/assets/chart-[^"]+\.js"#', $html);
    }
}
