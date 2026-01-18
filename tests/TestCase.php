<?php

namespace Thirtybittech\SafeCheck\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Thirtybittech\SafeCheck\ServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
        ];
    }
}
