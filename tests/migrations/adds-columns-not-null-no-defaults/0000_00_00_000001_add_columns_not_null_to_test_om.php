<?php

class AddColumnsNotNullToTestOm extends \Illuminate\Database\Migrations\Migration
{
    public function up()
    {
        Schema::table('test_om', function (\Illuminate\Database\Schema\Blueprint $table) {
            // Excluding explicit defaults to test auto-defaults w/PTSOC.
            $table->boolean('boolean_no_default')->nullable(false);
            $table->date('date_no_default')->nullable(false);
            $table->dateTime('datetime_no_default')->nullable(false);
            $table->float('float_no_default')->nullable(false);
            $table->integer('integer_no_default')->nullable(false);
            $table->string('string_no_default')->nullable(false);
            $table->time('time_no_default')->nullable(false);
            $table->timestamp('timestamp_no_default')->nullable(false);
            $table->uuid('uuid_no_default')->nullable(false);
        });
    }

    public function down()
    {
        Schema::table('test_om', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('boolean_no_default');
            $table->dropColumn('date_no_default');
            $table->dropColumn('datetime_no_default');
            $table->dropColumn('float_no_default');
            $table->dropColumn('integer_no_default');
            $table->dropColumn('string_no_default');
            $table->dropColumn('time_no_default');
            $table->dropColumn('timestamp_no_default');
            $table->dropColumn('uuid_no_default');
        });
    }
}
