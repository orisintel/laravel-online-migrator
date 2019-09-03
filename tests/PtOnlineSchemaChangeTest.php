<?php

namespace OrisIntel\OnlineMigrator\Tests;


use Illuminate\Support\Arr;
use OrisIntel\OnlineMigrator\Strategy\PtOnlineSchemaChange;

class PtOnlineSchemaChangeTest extends TestCase
{
    // TODO: Find a way to confirm commands executed through PTOSC since
    // getting output from $this->artisan, Artisan::call, and console Kernel
    // aren't working yet, and loadMigrationsFrom is opaque.

    public function test_getOptionsForShell_overridesDefault()
    {
        $this->assertEquals(
            ' --alter-foreign-keys-method=none',
            PtOnlineSchemaChange::getOptionsForShell('--alter-foreign-keys-method=none', ['--alter-foreign-keys-method=auto']));
    }

    public function test_getQueryOrCommand_doesntRewriteTableRename()
    {
        $query = ['query' => 'ALTER TABLE `t` RENAME `t2`'];

        $query_or_command = PtOnlineSchemaChange::getQueryOrCommand($query, \DB::connection());
        $this->assertEquals($query['query'], $query_or_command);
    }

    public function test_getQueryOrCommand_rewritesDropForeignKey()
    {
        $query = ['query' => 'ALTER TABLE t DROP FOREIGN KEY fk, DROP FOREIGN KEY fk2'];

        $command = PtOnlineSchemaChange::getQueryOrCommand($query, \DB::connection());
        $this->assertStringStartsWith('pt-online-schema-change', $command);
        $this->assertContains("'DROP FOREIGN KEY _fk, DROP FOREIGN KEY _fk2", $command);
    }

    public function test_getQueryOrCommand_rewritesDropIndex()
    {
        $query = ['query' => 'DROP INDEX idx ON test'];

        $command = PtOnlineSchemaChange::getQueryOrCommand($query, \DB::connection());
        $this->assertStringStartsWith('pt-online-schema-change', $command);
        $this->assertContains("'DROP INDEX", $command);
        $this->assertNotContains(' ON test ', $command);
    }

    public function test_getQueryOrCommand_supportsAnsiQuotes()
    {
        $query = ['query' => 'ALTER TABLE "t" ADD "c" INT, DROP "c2", DROP FOREIGN KEY "fk"'];

        $command = PtOnlineSchemaChange::getQueryOrCommand($query, \DB::connection());
        $this->assertStringStartsWith('pt-online-schema-change', $command);
        $this->assertContains("'ADD \"c\" INT, DROP \"c2\", DROP FOREIGN KEY \"_fk\"", $command);
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
            Arr::last(
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
            Arr::last(
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

    public function test_migrate_combinesAdjacentDdl()
    {
        $queries = [
            ['query' => 'ALTER TABLE t ADD c INT'],
            ['query' => 'ALTER TABLE t ALTER c2 SET DEFAULT 0'],
            ['query' => 'ALTER TABLE t DROP c3'],
            ['query' => 'ALTER TABLE t CHANGE c4 c4 TEXT'],
            ['query' => 'ALTER TABLE t2 ADD c INT'],
            ['query' => 'ALTER TABLE t2 ADD c2 INT'],
        ];

        $converted = PtOnlineSchemaChange::getQueriesAndCommands($queries, \DB::connection());
        $this->assertCount(2, $converted);
        $this->assertStringStartsWith('pt-online-schema-change', $converted[0]['query']);
        $this->assertContains('ADD c INT, ALTER c2 SET DEFAULT 0, DROP c3, CHANGE c4 c4 TEXT', $converted[0]['query']);
        $this->assertContains('ADD c INT, ADD c2 INT', $converted[1]['query']);
    }

    public function test_migrate_doesNotCombineUnsupportedSql()
    {
        $queries = [
            ['query' => 'ALTER TABLE t ADD c INT'],
            ['query' => 'ALTER TABLE t EXCHANGE PARTITION p WITH TABLE t2'],
        ];

        $converted = PtOnlineSchemaChange::getQueriesAndCommands($queries, \DB::connection());
        $this->assertCount(2, $converted);
        $this->assertNotContains(", EXCHANGE PARTITION", $converted[0]['query']);
    }

    public function test_migrate_dropsIndexWithSql()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/creates-index-with-raw-sql');
        $this->loadMigrationsFrom(__DIR__ . '/migrations/drops-index-with-raw-sql');

        $show_create_sql = str_replace('`', '',
            Arr::last(
                \DB::select('show create table test_om')
            )->{"Create Table"}
        );

        $this->assertNotContains('FULLTEXT', $show_create_sql);
    }
}
