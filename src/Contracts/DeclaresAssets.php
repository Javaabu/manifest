<?php

namespace Javaabu\Manifest\Contracts;

/**
 * For Blade components whose assets depend on their props.
 */
interface DeclaresAssets
{
    /**
     * @return array<int, string> asset ids (package::name) or package names
     */
    public function requiredAssets(): array;
}
