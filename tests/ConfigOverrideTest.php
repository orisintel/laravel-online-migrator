<?php

namespace OrisIntel\OnlineMigrator\Tests;


class ConfigOverrideTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('online-migrator.enabled', '1');
        $app['config']->set('online-migrator.strategy', 'pt-online-schema-change');
    }

    public function test_config_overriddenByCliEnv()
    {
        putenv('ONLINE_MIGRATOR=0');
        // Known to be unsupported by PTOSC (v3) for the time being, so this
        // provides indirect proof that the CLI env override bypasses PTOSC.
        $this->loadMigrationsFrom(__DIR__ . '/migrations/adds-without-default');

        $this->assertEquals('column added', \DB::table('test_om')->first()->without_default ?? null);
    }

    public function tearDown(): void
    {
        putenv('ONLINE_MIGRATOR=');
        $this->app['config']->set('online-migrator.enabled', '');
        parent::tearDown();
    }
}
