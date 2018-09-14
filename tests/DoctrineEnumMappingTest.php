<?php

namespace OrisIntel\OnlineMigrator\Tests;

class DoctrineEnumMappingTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('online-migrator.strategy', 'innodb-online-ddl');
    }

    public function test_migrate_altersWithEnum()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/alter-with-enum');

        $name_max_length = \DB::table('information_schema.columns')
            ->where('table_name', 'test_om')
            ->where('column_name', 'name')
            ->value('CHARACTER_MAXIMUM_LENGTH');
        $this->assertEquals(150, $name_max_length);
    }
}
