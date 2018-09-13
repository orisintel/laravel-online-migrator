<?php

class ChangeTypeOfNameInTestOm extends \Illuminate\Database\Migrations\Migration
{
    public function up()
    {
        Schema::table('test_om', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->text('name')->change();
        });

        \DB::table('test_om')->where('id', 1)
            ->update(['name' => str_repeat('x', 65535)]);
    }

    public function down()
    {
        \DB::table('test_om')->whereRaw('191 < LENGTH(name)')
            ->update(['name' => \DB::raw('SUBSTRING(name FROM 1 FOR 191)')]);

        Schema::table('test_om', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->string('name', 191)->change();
        });
    }
}
