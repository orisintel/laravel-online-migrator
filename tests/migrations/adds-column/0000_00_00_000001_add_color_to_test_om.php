<?php

class AddColorToTestOm extends \Illuminate\Database\Migrations\Migration
{
    public function up()
    {
        Schema::table('test_om', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->string('color')->nullable();
        });

        \DB::table('test_om')->where(['name' => 'one'])->update(['color' => 'green']);
    }

    public function down()
    {
        Schema::table('test_om', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('color');
        });
    }
}
