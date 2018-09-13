<?php

class ChangeEngineOfTestOm extends \Illuminate\Database\Migrations\Migration
{
    public function up()
    {
        \DB::statement('ALTER TABLE test_om ENGINE=MYISAM');
    }

    public function down()
    {
        \DB::statement('ALTER TABLE test_om ENGINE=INNODB');
    }
}
