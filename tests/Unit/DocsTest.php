<?php

namespace Javaabu\Manifest\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DocsTest extends TestCase
{
    protected function root(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path ? "/{$path}" : '');
    }

    /**
     * @return array<int, string>
     */
    protected function pages(): array
    {
        $pages = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root('docs'))) as $file) {
            if ($file->getExtension() === 'md') {
                $pages[] = substr($file->getPathname(), strlen($this->root()) + 1);
            }
        }

        sort($pages);

        return $pages;
    }

    #[Test]
    public function it_links_every_docs_page_from_llms_txt(): void
    {
        $llms = file_get_contents($this->root('llms.txt'));

        foreach ($this->pages() as $page) {
            $this->assertStringContainsString("]({$page})", $llms, "{$page} is not linked from llms.txt.");
        }
    }

    #[Test]
    public function it_has_no_broken_relative_links(): void
    {
        $files = [...$this->pages(), 'llms.txt', 'README.md', 'resources/boost/skills/javaabu-manifest/SKILL.md'];

        foreach ($files as $file) {
            $contents = file_get_contents($this->root($file));

            preg_match_all('/\]\(((?!https?:|mailto:|#)[^)\s]+)\)/', $contents, $matches);

            foreach ($matches[1] as $link) {
                $target = preg_replace('/#.*$/', '', $link);
                $base = dirname($this->root($file));

                $candidates = [
                    "{$base}/{$target}",
                    "{$base}/{$target}.md",
                    "{$base}/" . rtrim($target, '/') . '/_category_.json',
                ];

                $exists = array_filter($candidates, fn (string $candidate) => file_exists($candidate));

                $this->assertNotEmpty($exists, "Broken link [{$link}] in {$file}.");
            }
        }
    }

    #[Test]
    public function every_page_has_a_title(): void
    {
        foreach ($this->pages() as $page) {
            $this->assertMatchesRegularExpression('/^---\ntitle: .+\n/', file_get_contents($this->root($page)), "{$page} has no title.");
        }
    }

    #[Test]
    public function the_documented_config_matches_the_config_file(): void
    {
        $config = file_get_contents($this->root('config/manifest.php'));
        $docs = file_get_contents($this->root('docs/installation-and-setup.md'));

        preg_match('/^return \[.*?^\];/ms', $config, $expected);

        $this->assertStringContainsString($expected[0], $docs);
    }

    #[Test]
    public function it_ships_the_boost_guideline_and_skill(): void
    {
        $this->assertFileExists($this->root('resources/boost/guidelines/core.blade.php'));

        $skill = file_get_contents($this->root('resources/boost/skills/javaabu-manifest/SKILL.md'));

        $this->assertMatchesRegularExpression('/^---\nname: javaabu-manifest\ndescription: .+\n/', $skill);
    }
}
