<?php

namespace OrisIntel\OnlineMigrator\Tests;


class ConfigTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('online-migrator.enabled', '0');
        $app['config']->set('online-migrator.strategy', 'pt-online-schema-change');
    }

    public function test_config_notEnabledAddsWithoutDefault()
    {
        // Known to be unsupported by PTOSC (v3) for the time being, so this
        // provides indirect proof disabled bypasses PTOSC.
        $this->loadMigrationsFrom(__DIR__ . '/migrations/adds-without-default');

        $this->assertEquals('column added', \DB::table('test_om')->first()->without_default ?? null);
    }

    public function tearDown(): void
    {
        // Reset to blank for later tests.
        $this->app['config']->set('online-migrator.enabled', '');
        parent::tearDown();
    }
}
