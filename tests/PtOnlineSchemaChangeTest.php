<?php

namespace OrisIntel\OnlineMigrator\Tests;


use OrisIntel\OnlineMigrator\Strategy\PtOnlineSchemaChange;

class PtOnlineSchemaChangeTest extends TestCase
{
    // TODO: Find a way to confirm commands executed through PTOSC since
    // getting output from $this->artisan, Artisan::call, and console Kernel
    // aren't working yet, and loadMigrationsFrom is opaque.

    public function test_getQueryOrCommand_rewritesDropIndex()
    {
        $query = ['query' => 'DROP INDEX idx ON test'];

        $command = PtOnlineSchemaChange::getQueryOrCommand($query, \DB::connection());
        $this->assertStringStartsWith('pt-online-schema-change', $command);
        $this->assertContains("'DROP INDEX", $command);
        $this->assertNotContains(' ON test ', $command);
    }

    public function test_migrate_addsColumn()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/adds-column');

        $test_row_one = \DB::table('test_om')->where('name', 'one')->first();
        $this->assertNotNull($test_row_one);
        $this->assertEquals('green', $test_row_one->color);
    }

    public function test_migrate_addsUnique()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/adds-unique');

        $this->expectException(\PDOException::class);
        $this->expectExceptionCode(23000);
        \DB::table('test_om')->insert(['name' => 'one']);
    }

    public function test_migrate_addsWithoutDefault()
    {
        // Known to be unsupported by PTOSC (v3) for the time being, so this
        // provides indirect proof that it's working through PTOSC.
        $this->expectException(\UnexpectedValueException::class);
        // HACK: Workaround Travis CI passthru return_var differences from local.
        $this->expectExceptionCode(getenv('TRAVIS') ? 255 : 29);
        $this->loadMigrationsFrom(__DIR__ . '/migrations/adds-without-default');
    }

    public function test_migrate_changesType()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/changes-type');

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

        // PTOSC will duplicate where native does not because of differences in
        // default naming and detection of existing indexes.
        // Workaround in real migrations by moving FK(s) creation to its own
        // Schema::table() call separate from column creation.
        preg_match_all('~^\s+KEY\s+([^\s]+)~mu', $show_create_sql, $m);
        $this->assertEquals([
            'test_om_fk_with_index_test_om_id_foreign',
            'test_om_fk_with_index_test_om_id_index',
        ], $m[1]);
    }

    public function test_migrate_createsIndexWithSql()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/creates-index-with-raw-sql');

        $show_create_sql = str_replace('`', '',
            array_last(
                \DB::select('show create table test_om')
            )->{"Create Table"}
        );

        $this->assertContains('FULLTEXT', $show_create_sql);
    }

    public function test_migrate_createsTableWithPrimary()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/creates-table-with-primary');

        $this->expectException(\PDOException::class);
        $this->expectExceptionCode(23000);
        \DB::table('test_om_with_primary')->insert(['name' => 'alice']);
    }

    public function test_migrate_dropsIndexWithSql()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/creates-index-with-raw-sql');
        $this->loadMigrationsFrom(__DIR__ . '/migrations/drops-index-with-raw-sql');

        $show_create_sql = str_replace('`', '',
            array_last(
                \DB::select('show create table test_om')
            )->{"Create Table"}
        );

        $this->assertNotContains('FULLTEXT', $show_create_sql);
    }
}
