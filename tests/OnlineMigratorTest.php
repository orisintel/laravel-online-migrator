<?php

namespace OrisIntel\OnlineMigrator\Tests;


class OnlineMigratorTest extends TestCase
{
    // TODO: Find a way to confirm commands executed through PTOSC since
    // getting output from $this->artisan, Artisan::call, and console Kernel
    // aren't working yet, and loadMigrationsFrom is opaque.

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
}