<?php

namespace Javaabu\Manifest\Tests\Unit;

use Javaabu\Manifest\Support\NpmVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NpmVersionTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: ?bool}>
     */
    public static function cases(): array
    {
        return [
            'any' => ['1.2.3', '*', true],
            'empty' => ['1.2.3', '', true],
            'exact' => ['1.2.3', '1.2.3', true],
            'exact miss' => ['1.2.4', '=1.2.3', false],
            'caret' => ['1.9.0', '^1.2.3', true],
            'caret below' => ['1.2.2', '^1.2.3', false],
            'caret next major' => ['2.0.0', '^1.2.3', false],
            'caret zero minor' => ['0.2.9', '^0.2.3', true],
            'caret zero minor next' => ['0.3.0', '^0.2.3', false],
            'caret zero zero' => ['0.0.4', '^0.0.3', false],
            'caret major only' => ['19.3.1', '^19', true],
            'tilde' => ['1.2.9', '~1.2.3', true],
            'tilde next minor' => ['1.3.0', '~1.2.3', false],
            'tilde major only' => ['1.9.0', '~1', true],
            'x range' => ['1.4.0', '1.x', true],
            'x range miss' => ['2.0.0', '1.x', false],
            'partial' => ['1.2.7', '1.2', true],
            'gte' => ['2.0.0', '>=1.5', true],
            'gt partial' => ['1.5.9', '>1.5', false],
            'gt' => ['1.6.0', '>1.5', true],
            'lte partial' => ['1.5.9', '<=1.5', true],
            'lt' => ['1.5.0', '<1.5.0', false],
            'and' => ['1.5.0', '>=1.2.0 <2.0.0', true],
            'and with spaces' => ['2.1.0', '>= 1.2.0 < 2.0.0', false],
            'hyphen' => ['1.5.0', '1.2.0 - 1.8.0', true],
            'or' => ['19.1.0', '^18.0.0 || ^19.0.0', true],
            'or miss' => ['17.0.2', '^18.0.0 || ^19.0.0', false],
            'prerelease ignored' => ['19.0.0-rc.1', '^19.0.0', true],
            'v prefix' => ['v1.2.3', '^1.0', true],
            'github' => ['1.0.0', 'github:Javaabu/react-forms#main', null],
            'file' => ['1.0.0', 'file:vendor/javaabu/manifest', null],
            'tag' => ['1.0.0', 'next', null],
            'unparseable version' => ['main', '^1.0', null],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function it_checks_npm_version_ranges(string $version, string $constraint, ?bool $expected): void
    {
        $this->assertSame($expected, NpmVersion::satisfies($version, $constraint));
    }
}
