<?php

namespace OrisIntel\OnlineMigrator;

use Illuminate\Database\MigrationServiceProvider;

class OnlineMigratorServiceProvider extends MigrationServiceProvider
{
    /**
     * Register the migrator service.
     *
     * @return void
     */
    public function registerMigrator()
    {
        // Provides hook to send SQL DDL changes through pt-online-schema-change in CLI
        $this->app->singleton('migrator', function ($app) {
            $respository = $app['migration.repository'];

            return new OnlineMigrator($respository, $app['db'], $app['files']);
        });
    }
}
