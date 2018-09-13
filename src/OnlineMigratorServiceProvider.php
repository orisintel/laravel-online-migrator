<?php

namespace OrisIntel\OnlineMigrator;

use Illuminate\Database\MigrationServiceProvider;

class OnlineMigratorServiceProvider extends MigrationServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/online-migrator.php' => config_path('online-migrator.php'),
            ], 'config');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/online-migrator.php', 'online-migrator');

        parent::register();
    }

    /**
     * Register the migrator service.
     *
     * @return void
     */
    public function registerMigrator()
    {
        // Provides hook to send SQL DDL changes through pt-online-schema-change in CLI
        $this->app->singleton('migrator', function ($app) {
            return new OnlineMigrator($app['migration.repository'], $app['db'], $app['files']);
        });
    }
}
