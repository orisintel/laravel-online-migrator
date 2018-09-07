<?php

class CreateTestTables extends \Illuminate\Database\Migrations\Migration
{
    public function up()
    {
        Schema::create('test_om', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->string('name', 191); // Workaround default key limits.
            $table->timestamps();
        });

        \DB::table('test_om')->insert(['name' => 'one']);
    }

    public function down()
    {
        Schema::drop('test_om');
    }
}
