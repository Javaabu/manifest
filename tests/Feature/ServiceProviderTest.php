<?php

namespace Javaabu\Manifest\Tests\Feature;

use Javaabu\Manifest\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_the_default_config(): void
    {
        $this->assertTrue(config('manifest.inject'));
    }
}
