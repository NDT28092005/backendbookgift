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
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id('preference_id');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('preferred_occasion', 100)->nullable();
            $table->string('favorite_category', 100)->nullable();
            $table->decimal('budget_range_min', 10, 2)->nullable();
            $table->decimal('budget_range_max', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_preferences');
    }
};
