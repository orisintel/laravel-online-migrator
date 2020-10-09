<?php

namespace OrisIntel\OnlineMigrator\Tests;


use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Drop residual tables here since config unavailable in `tearDown`.
        $table_names = DB::table("information_schema.tables")
            ->where('table_schema', '=', DB::getDatabaseName())
            ->pluck('TABLE_NAME');
        Schema::disableForeignKeyConstraints();
        foreach ($table_names as $table_name) {
            Schema::drop($table_name);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('test_om', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191); // Workaround default key limits.
            $table->timestamps();
        });

        DB::table('test_om')->insert(['name' => 'one']);
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
