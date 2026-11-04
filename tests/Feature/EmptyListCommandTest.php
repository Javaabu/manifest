<?php

namespace Javaabu\Manifest\Tests\Feature;

use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EmptyListCommandTest extends TestCase
{
    protected bool $with_fixture_packages = false;

    #[Test]
    public function it_says_when_no_packages_have_registered_assets(): void
    {
        $this->artisan('manifest:list')
            ->expectsOutputToContain('No packages have registered assets.')
            ->assertSuccessful();
    }
}
