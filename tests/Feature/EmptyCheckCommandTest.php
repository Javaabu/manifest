<?php

namespace Javaabu\Manifest\Tests\Feature;

use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EmptyCheckCommandTest extends TestCase
{
    protected bool $with_fixture_packages = false;

    #[Test]
    public function it_says_when_no_package_needs_npm(): void
    {
        $this->artisan('manifest:check')
            ->expectsOutputToContain('No package needs anything from npm.')
            ->assertSuccessful();
    }
}
