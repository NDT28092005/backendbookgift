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
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id('payment_id');
                $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
                $table->enum('payment_method', ['momo', 'vnpay', 'bank_transfer', 'cod']);
                $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
                $table->string('transaction_id', 100)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
