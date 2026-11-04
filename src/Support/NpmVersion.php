<?php

namespace Javaabu\Manifest\Support;

/**
 * Just enough of npm's semver ranges to tell an app whether an installed
 * version fits what a package asks for: exact versions, x-ranges, ^, ~,
 * comparisons, hyphen ranges, spaces (and) and || (or). Prerelease tags are
 * ignored. Anything else (github:, file:, tags) can't be checked.
 */
class NpmVersion
{
    /**
     * @return bool|null null when the constraint isn't a version range
     */
    public static function satisfies(string $version, string $constraint): ?bool
    {
        $constraint = trim($constraint);

        if (preg_match('/^(github:|git[+:]|https?:|file:|link:|workspace:|npm:|[\w.-]+\/[\w.-]+)/', $constraint)) {
            return null;
        }

        $version = static::parse($version);

        if ($version === null) {
            return null;
        }

        foreach (explode('||', $constraint) as $alternative) {
            $result = static::satisfiesAll($version, trim($alternative));

            if ($result === null) {
                return null;
            }

            if ($result) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $version
     */
    protected static function satisfiesAll(array $version, string $range): ?bool
    {
        if ($range === '' || $range === '*' || strtolower($range) === 'x' || $range === 'latest') {
            return true;
        }

        if (preg_match('/^(\S+)\s+-\s+(\S+)$/', $range, $hyphen)) {
            $range = ">={$hyphen[1]} <={$hyphen[2]}";
        }

        $range = preg_replace('/(>=|<=|>|<|=|\^|~)\s+/', '$1', $range);

        foreach (preg_split('/\s+/', $range) as $comparator) {
            $result = static::satisfiesComparator($version, $comparator);

            if ($result !== true) {
                return $result;
            }
        }

        return true;
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $version
     */
    protected static function satisfiesComparator(array $version, string $comparator): ?bool
    {
        if (! preg_match('/^(\^|~|>=|<=|>|<|=)?v?(\d+|[xX*])(?:\.(\d+|[xX*]))?(?:\.(\d+|[xX*]))?(?:[-+].*)?$/', $comparator, $matches)) {
            return null;
        }

        $operator = $matches[1] ?? '';
        $parts = [];

        foreach ([2, 3, 4] as $index) {
            $part = $matches[$index] ?? '';

            $parts[] = ($part === '' || ! ctype_digit($part)) ? null : (int) $part;
        }

        [$major, $minor, $patch] = $parts;

        if ($major === null) {
            return true;
        }

        $lower = [$major, $minor ?? 0, $patch ?? 0];

        return match ($operator) {
            '^' => static::compare($version, $lower) >= 0 && static::compare($version, static::caretUpper($major, $minor, $patch)) < 0,
            '~' => static::compare($version, $lower) >= 0 && static::compare($version, $minor === null ? [$major + 1, 0, 0] : [$major, $minor + 1, 0]) < 0,
            '>=' => static::compare($version, $lower) >= 0,
            '>' => static::compare($version, static::partialUpper($major, $minor, $patch)) >= 0,
            '<=' => static::compare($version, static::partialUpper($major, $minor, $patch)) < 0,
            '<' => static::compare($version, $lower) < 0,
            default => static::compare($version, $lower) >= 0 && static::compare($version, static::partialUpper($major, $minor, $patch)) < 0,
        };
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected static function caretUpper(int $major, ?int $minor, ?int $patch): array
    {
        if ($major > 0 || $minor === null) {
            return [$major + 1, 0, 0];
        }

        if ($minor > 0 || $patch === null) {
            return [0, $minor + 1, 0];
        }

        return [0, 0, $patch + 1];
    }

    /**
     * The first version after a partial one: 1 -> 2.0.0, 1.2 -> 1.3.0, 1.2.3 -> 1.2.4.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    protected static function partialUpper(int $major, ?int $minor, ?int $patch): array
    {
        if ($minor === null) {
            return [$major + 1, 0, 0];
        }

        if ($patch === null) {
            return [$major, $minor + 1, 0];
        }

        return [$major, $minor, $patch + 1];
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    protected static function parse(string $version): ?array
    {
        if (! preg_match('/^v?(\d+)\.(\d+)\.(\d+)/', trim($version), $matches)) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $a
     * @param  array{0: int, 1: int, 2: int}  $b
     */
    protected static function compare(array $a, array $b): int
    {
        return $a <=> $b;
    }
}
