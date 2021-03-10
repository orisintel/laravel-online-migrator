<?php

namespace OrisIntel\OnlineMigrator\Tests;

class StrategyTraitTest extends TestCase
{
    public function setUp() : void
    {
        parent::setUp();

        \DB::enableQueryLog();
        \DB::flushQueryLog(); // SANITY: Should be unnecessary but just in case.
    }

    public function test_migrate_usesStrategyFromTrait()
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations/adds-column-with-online-ddl');

        $this->assertEquals(
            'alter table `test_om` add `color` varchar(255) null, ALGORITHM=INPLACE, LOCK=NONE',
            // HACK: Ignore unmodified copies of queries in log.
            \DB::getQueryLog()[3]['query']);

        $test_row_one = \DB::table('test_om')->where('name', 'one')->first();
        $this->assertNotNull($test_row_one);
        $this->assertEquals('green', $test_row_one->color);
    }
}
