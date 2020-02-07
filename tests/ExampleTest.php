<?php

namespace PKeidel\Laralog\Tests;

use Orchestra\Testbench\TestCase;
use PKeidel\Laralog\LaralogServiceProvider;

class ExampleTest extends TestCase
{

    protected function getPackageProviders($app)
    {
        return [LaralogServiceProvider::class];
    }

    /** @test */
    public function true_is_true()
    {
        $this->assertTrue(true);
    }
}
