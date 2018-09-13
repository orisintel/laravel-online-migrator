<?php

class DropIndexOnTestOmWithRawSql extends \Illuminate\Database\Migrations\Migration
{
    // Depends upon CreateIndexOnTestOmWithRawSql being run during setup.
    public function up()
    {
        \DB::statement('DROP INDEX test_om_idx ON test_om');
    }

    public function down()
    {
        \DB::statement('CREATE FULLTEXT INDEX test_om_idx ON test_om (name);');
    }
}
