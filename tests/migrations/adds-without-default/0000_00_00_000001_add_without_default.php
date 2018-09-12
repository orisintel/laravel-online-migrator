<?php

class AddWithoutDefault extends \Illuminate\Database\Migrations\Migration
{
    public function up()
    {
        Schema::table('test_om', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->string('without_default')->nullable(false);
        });

        \DB::table('test_om')->where('id', 1)->update(['without_default' => 'column added']);
    }

    public function down()
    {
        Schema::table('test_om', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('without_default');
        });
    }
}
