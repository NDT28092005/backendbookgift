<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->decimal('budget_range_min', 15, 2)->nullable()->change();
            $table->decimal('budget_range_max', 15, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->decimal('budget_range_min', 10, 2)->nullable()->change();
            $table->decimal('budget_range_max', 10, 2)->nullable()->change();
        });
    }
};

