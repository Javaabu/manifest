<?php

namespace Javaabu\Manifest\Tests\Feature;

use Acme\Filters\Components\FilterBar;
use Illuminate\Support\Facades\Blade;
use Javaabu\Manifest\Attributes\RequiresAssets;
use Javaabu\Manifest\Facades\Manifest;
use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ComponentsTest extends TestCase
{
    #[Test]
    public function it_requires_assets_declared_with_the_attribute(): void
    {
        $html = Blade::render('<x-filters::filter-bar label="ފިލްޓަރު" />');

        $this->assertStringContainsString('ފިލްޓަރު', $html);
        $this->assertTrue(Manifest::isRequired('filters::filter-bar'));
        $this->assertTrue(Manifest::isRequired('widgets::core'));
        $this->assertFalse(Manifest::isRequired('filters::date-range'));
    }

    #[Test]
    public function it_requires_assets_declared_by_the_component_from_its_props(): void
    {
        Blade::render('<x-filters::date-range :interactive="false" />');

        $this->assertTrue(Manifest::isRequired('filters::styles'));
        $this->assertFalse(Manifest::isRequired('filters::date-range'));

        Blade::render('<x-filters::date-range />');

        $this->assertTrue(Manifest::isRequired('filters::date-range'));
    }

    #[Test]
    public function it_requires_assets_from_anonymous_components(): void
    {
        Blade::render('<x-widgets::chart label="ޗާޓް" />');

        $this->assertTrue(Manifest::isRequired('widgets::chart'));
    }

    #[Test]
    public function it_inherits_attributes_from_parent_components(): void
    {
        Blade::component('special-filter-bar', SpecialFilterBar::class);

        Blade::render('<x-special-filter-bar />');

        $this->assertTrue(Manifest::isRequired('filters::filter-bar'));
        $this->assertTrue(Manifest::isRequired('widgets::chart'));
    }

    #[Test]
    public function it_returns_what_is_required_in_dependency_order(): void
    {
        Blade::render('<x-filters::filter-bar />');

        $ids = array_map(fn ($asset) => $asset->id(), Manifest::required());

        $this->assertEquals(['widgets::theme', 'widgets::core', 'filters::styles', 'filters::filter-bar'], $ids);
    }
}

#[RequiresAssets('widgets::chart')]
class SpecialFilterBar extends FilterBar {}
