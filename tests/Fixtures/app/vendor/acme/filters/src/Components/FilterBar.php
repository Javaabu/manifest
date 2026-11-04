<?php

namespace Acme\Filters\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Javaabu\Manifest\Attributes\RequiresAssets;

#[RequiresAssets('filters::filter-bar')]
class FilterBar extends Component
{
    public function __construct(
        public string $label = 'ފިލްޓަރު',
    ) {}

    public function render(): View
    {
        return view('filters::components.filter-bar');
    }
}
