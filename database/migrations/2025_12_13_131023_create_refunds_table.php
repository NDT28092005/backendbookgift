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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_request_id');
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->string('method')->default('bank');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('return_request_id')
                ->references('id')
                ->on('return_requests')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('refunds');
    }
};
