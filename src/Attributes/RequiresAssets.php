<?php

namespace Javaabu\Manifest\Attributes;

use Attribute;

/**
 * Put on a Blade component class: whenever the component renders, the
 * assets (package::name, or a package name for all its assets) are loaded.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class RequiresAssets
{
    /** @var array<int, string> */
    public array $assets;

    public function __construct(string ...$assets)
    {
        $this->assets = array_values($assets);
    }
}
