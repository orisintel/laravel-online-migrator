<?php

namespace OrisIntel\OnlineMigrator\Tests;

use OrisIntel\OnlineMigrator\Strategy\InnodbOnlineDdl;

class InnodbOnlineDdlTest extends TestCase
{
    public function setUp() : void
    {
        parent::setUp();

        // CONSIDER: Using \DB::listen instead.
        \DB::enableQueryLog();
        \DB::flushQueryLog(); // SANITY: Should be unnecessary but just in case.
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('online-migrator.strategy', 'innodb-online-ddl');
    }

    public function test_getQueryOrCommand_algorithmCopyWhenAddFkChecksOn()
    {
        $query = [
            'query' => 'ALTER TABLE test ADD FOREIGN KEY (my_fk_id) REFERENCES test_om2 (id)',
        ];
        $this->assertStringEndsWith(
            ', ALGORITHM=COPY, LOCK=SHARED',
            InnodbOnlineDdl::getQueryOrCommand($query, \DB::connection())
        );
    }

    public function test_getQueryOrCommand_algorithmInplaceWhenAddFkChecksOff()
    {
        $connection = \DB::connection();
        $connection->statement('SET foreign_key_checks=OFF');
        $query = [
            'query' => 'ALTER TABLE test ADD FOREIGN KEY (my_fk_id) REFERENCES test_om2 (id)',
        ];
        $this->assertStringEndsWith(
            ', ALGORITHM=INPLACE, LOCK=NONE',
            InnodbOnlineDdl::getQueryOrCommand($query, $connection)
        );
    }

    public function test_getQueryOrCommand_algorithmCopyWhenDropPk()
    {
        $query = [
            'query' => 'ALTER TABLE test DROP PRIMARY KEY',
        ];
        $this->assertStringEndsWith(
            ', ALGORITHM=COPY, LOCK=SHARED',
            InnodbOnlineDdl::getQueryOrCommand($query, \DB::connection())
        );
    }

    public function test_getQueryOrCommand_algorithmInplaceWhenDropAddPk()
    {
        $query = [
            'query' => 'ALTER TABLE test DROP PRIMARY KEY, ADD PRIMARY KEY (new_id)',
        ];
        $this->assertStringEndsWith(
            ', ALGORITHM=INPLACE, LOCK=NONE',
            InnodbOnlineDdl::getQueryOrCommand($query, \DB::connection())
        );
    }

    public function test_getQueryOrCommand_rewritesDropIndex()
    {
        $query = ['query' => 'DROP INDEX idx ON test'];

        $this->assertStringEndsWith(
            ' ALGORITHM=INPLACE LOCK=NONE',
            InnodbOnlineDdl::getQueryOrCommand($query, \DB::connection())
        );
    }

    public function test_migrate_addsColumn()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/adds-column');

        $this->assertEquals(
            'alter table `test_om` add `color` varchar(255) null, ALGORITHM=INPLACE, LOCK=NONE',
            // HACK: Ignore unmodified copies of queries in log.
            // CONSIDER: Fixing implementation to avoid dupes in query log.
            \DB::getQueryLog()[2]['query']);

        $test_row_one = \DB::table('test_om')->where('name', 'one')->first();
        $this->assertNotNull($test_row_one);
        $this->assertEquals('green', $test_row_one->color);
    }

    public function test_migrate_addsUnique()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/adds-unique');

        $this->assertEquals(
            'alter table `test_om` add unique `test_om_name_unique`(`name`), ALGORITHM=INPLACE, LOCK=NONE',
            // HACK: Ignore unmodified copies of queries in log.
            \DB::getQueryLog()[1]['query']);

        $this->expectException(\PDOException::class);
        $this->expectExceptionCode(23000);
        \DB::table('test_om')->insert(['name' => 'one']);
    }

    public function test_migrate_addsWithoutDefault()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/adds-without-default');

        $this->assertEquals(
            'alter table `test_om` add `without_default` varchar(255) not null, ALGORITHM=INPLACE, LOCK=NONE',
            // HACK: Ignore unmodified copies of queries in log.
            \DB::getQueryLog()[2]['query']);

        $this->assertEquals('column added', \DB::table('test_om')->first()->without_default ?? null);
    }

    public function test_migrate_changesType()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/changes-type');

        $this->assertStringEndsWith(', ALGORITHM=COPY, LOCK=SHARED',
            // HACK: Ignore unmodified copies of queries in log.
            \DB::getQueryLog()[2]['query']);

        $expanded_name = \DB::table('test_om')->where('id', 1)->value('name');
        $this->assertEquals(65535, mb_strlen($expanded_name));
    }

    public function test_migrate_createsFkWithIndex()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/creates-fk-with-index');

        $show_create_sql = str_replace('`', '',
            array_last(
                \DB::select('show create table test_om_fk_with_index')
            )->{"Create Table"}
        );

        preg_match_all('~^\s+KEY\s+([^\s]+)~mu', $show_create_sql, $m);
        // Unlike PTOSC, InnoDB's Online DDL should not create redundant indexes.
        $this->assertEquals([
            'test_om_fk_with_index_test_om_id_index',
        ], $m[1]);
    }

    public function test_migrate_createsIndexWithSql()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/creates-index-with-raw-sql');

        $this->assertStringEndsWith(' ALGORITHM=INPLACE LOCK=SHARED',
            // HACK: Ignore unmodified copies of queries in log.
            \DB::getQueryLog()[1]['query']);
    }

    public function test_migrate_createsTableWithPrimary()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/creates-table-with-primary');

        $this->expectException(\PDOException::class);
        $this->expectExceptionCode(23000);
        \DB::table('test_om_with_primary')->insert(['name' => 'alice']);
    }
}
