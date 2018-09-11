<?php

class CreateTableWithPrimary extends \Illuminate\Database\Migrations\Migration
{
    public function up()
    {
        Schema::create('test_om_with_primary', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->string('name', 191)->primary();
        });

        \DB::table('test_om_with_primary')->insert(['name' => 'alice']);
    }

    public function down()
    {
        Schema::drop('test_om_with_primary');
    }
}
