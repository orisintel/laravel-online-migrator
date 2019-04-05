<?php

class CreateFkWithIndex extends \Illuminate\Database\Migrations\Migration
{
    use \OrisIntel\OnlineMigrator\CombineIncompatible;

    public function up()
    {
        Schema::create('test_om_fk_with_index', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->increments('id');
            $table->integer('test_om_id')->unsigned()->index();

            $table->foreign('test_om_id')
                ->references('id')
                ->on('test_om')
                ->onDelete('cascade');
        });

        \DB::table('test_om_fk_with_index')->insert(['test_om_id' => 1]);
    }

    public function down()
    {
        Schema::drop('test_om_fk_with_index');
    }
}
