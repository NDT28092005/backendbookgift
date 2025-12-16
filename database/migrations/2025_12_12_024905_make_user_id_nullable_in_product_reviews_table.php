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
        Schema::table('product_reviews', function (Blueprint $table) {
            // Drop foreign key constraint trước
            $table->dropForeign(['user_id']);
            
            // Thay đổi user_id thành nullable
            $table->foreignId('user_id')->nullable()->change();
            
            // Tạo lại foreign key với nullOnDelete
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            // Drop foreign key
            $table->dropForeign(['user_id']);
            
            // Đổi lại thành not nullable
            $table->foreignId('user_id')->nullable(false)->change();
            
            // Tạo lại foreign key không nullable
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
