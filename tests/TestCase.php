<?php

namespace OrisIntel\OnlineMigrator\Tests;


class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/migrations/setup');
    }

    protected function getPackageProviders($app) {
        return ['\OrisIntel\OnlineMigrator\OnlineMigratorServiceProvider'];
    }
}
