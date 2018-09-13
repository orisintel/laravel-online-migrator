<?php

class CreateIndexOnTestOmWithRawSql extends \Illuminate\Database\Migrations\Migration
{
    public function up()
    {
        \DB::statement('CREATE FULLTEXT INDEX test_om_idx ON test_om (name);');
    }

    public function down()
    {
        \DB::statement('DROP INDEX test_om_idx ON test_om');
    }
}
