<?php

namespace Javaabu\Manifest\Tests\Unit;

use Javaabu\Manifest\Enums\AssetTypes;
use Javaabu\Manifest\Exceptions\InvalidAssetException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AssetTypesTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: AssetTypes}>
     */
    public static function paths(): array
    {
        return [
            'css' => ['css/app.css', AssetTypes::CSS],
            'scss' => ['css/app.scss', AssetTypes::SASS],
            'sass' => ['css/app.sass', AssetTypes::SASS],
            'js' => ['js/app.js', AssetTypes::JS],
            'mjs' => ['js/app.mjs', AssetTypes::JS],
            'ts' => ['js/app.ts', AssetTypes::TS],
            'tsx' => ['js/App.tsx', AssetTypes::REACT],
            'jsx' => ['js/App.JSX', AssetTypes::REACT],
            'vue' => ['js/App.vue', AssetTypes::VUE],
        ];
    }

    #[Test]
    #[DataProvider('paths')]
    public function it_infers_the_type_from_the_extension(string $path, AssetTypes $expected): void
    {
        $this->assertEquals($expected, AssetTypes::fromPath($path));
    }

    #[Test]
    public function it_rejects_unsupported_extensions(): void
    {
        $this->expectException(InvalidAssetException::class);

        AssetTypes::fromPath('images/logo.png');
    }

    #[Test]
    public function it_knows_styles_from_scripts(): void
    {
        $this->assertTrue(AssetTypes::SASS->isStyle());
        $this->assertFalse(AssetTypes::SASS->isScript());
        $this->assertTrue(AssetTypes::VUE->isScript());
        $this->assertFalse(AssetTypes::TS->isStyle());
    }

    #[Test]
    public function it_lists_the_npm_packages_needed_to_build_each_type(): void
    {
        $this->assertEquals(['sass'], AssetTypes::SASS->buildRequirements());
        $this->assertContains('@vitejs/plugin-react', AssetTypes::REACT->buildRequirements());
        $this->assertContains('vue', AssetTypes::VUE->buildRequirements());
        $this->assertEquals([], AssetTypes::CSS->buildRequirements());
    }
}
