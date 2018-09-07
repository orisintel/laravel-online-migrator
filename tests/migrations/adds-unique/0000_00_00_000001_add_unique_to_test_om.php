<?php

class AddUniqueToTestOm extends \Illuminate\Database\Migrations\Migration
{
    public function up()
    {
        Schema::table('test_om', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down()
    {
        Schema::table('test_om', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
}
