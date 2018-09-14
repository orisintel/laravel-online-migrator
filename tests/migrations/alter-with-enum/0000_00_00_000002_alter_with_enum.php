<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterWithEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('test_om', function (Blueprint $table) {
            $table->string('name', 150)->change();
            /* TODO: Support changing enum column itself.
            $table->enum('my_enum', ['A', 'B', 'C'])->change();
            */
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('test_om', function (Blueprint $table) {
            $table->string('name', 191)->change();
            /* TODO: Support changing enum column itself.
            $table->enum('my_enum', ['A', 'B'])->change();
            */
        });
    }
}
