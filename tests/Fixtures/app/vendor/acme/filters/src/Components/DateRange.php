<?php

namespace Acme\Filters\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Javaabu\Manifest\Contracts\DeclaresAssets;

class DateRange extends Component implements DeclaresAssets
{
    public function __construct(
        public bool $interactive = true,
    ) {}

    public function requiredAssets(): array
    {
        return $this->interactive
            ? ['filters::date-range']
            : ['filters::styles'];
    }

    public function render(): string
    {
        return '<div data-date-range></div>';
    }
}
