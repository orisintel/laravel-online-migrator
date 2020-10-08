<?php

namespace OrisIntel\OnlineMigrator\Tests;


class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // HACK: Workaround failing on Travis unless explicitly truthy.
        $this->app['config']->set('online-migrator.enabled', '1');
        $this->loadMigrationsFrom(__DIR__ . '/migrations/setup');
    }

    protected function getEnvironmentSetUp($app)
    {
        // Don't want residual enums to get in the way of other tests, so always
        // map to string.
        // CONSIDER: Further isolating tests, each with it's own table(s).
        $app['config']->set('online-migrator.doctrine-enum-mapping', 'string');
    }

    protected function getPackageProviders($app)
    {
        return ['\OrisIntel\OnlineMigrator\OnlineMigratorServiceProvider'];
    }
}
