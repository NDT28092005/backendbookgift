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
        Schema::create('ghtk_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique()->nullable(); // Mã đơn GHTK trả về
            $table->unsignedBigInteger('order_id'); // Liên kết với bảng orders
            $table->string('label_id')->nullable(); // Mã vận đơn GHTK
            $table->string('partner_id')->nullable(); // Partner ID nếu có
            $table->string('status')->default('created'); // Trạng thái: created | shipping | delivered | cancelled
            $table->decimal('fee', 10, 2)->nullable(); // Phí vận chuyển
            $table->string('tracking_url')->nullable(); // Link tra cứu
            $table->json('response')->nullable(); // Toàn bộ response GHTK
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ghtk_orders');
    }
};
