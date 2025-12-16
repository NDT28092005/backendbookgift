<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1️⃣ Cập nhật bảng orders
        Schema::table('orders', function (Blueprint $table) {
            // Nếu chưa có expires_at thì thêm vào
            if (!Schema::hasColumn('orders', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('status');
            }
        });

        // 2️⃣ Sửa bảng cart_items (nếu cần)
        if (Schema::hasTable('cart_items')) {
            Schema::table('cart_items', function (Blueprint $table) {
                if (!Schema::hasColumn('cart_items', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('cart_id');
                }
            });
        }

        // 3️⃣ Chuẩn hóa bảng order_items
        if (Schema::hasTable('order_items')) {
            // Đổi tên cột order_item_id về id nếu cần (MariaDB compatible)
            if (Schema::hasColumn('order_items', 'order_item_id') && !Schema::hasColumn('order_items', 'id')) {
                DB::statement('ALTER TABLE `order_items` CHANGE `order_item_id` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            }
        }
    }

    public function down(): void
    {
        // Khôi phục lại cấu trúc cũ nếu rollback
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'cancelled'
            ])->default('pending')->change();

            $table->dropColumn('expires_at');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            if (Schema::hasColumn('cart_items', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });

        // Rollback rename column (MariaDB compatible)
        if (Schema::hasColumn('order_items', 'id') && !Schema::hasColumn('order_items', 'order_item_id')) {
            DB::statement('ALTER TABLE `order_items` CHANGE `id` `order_item_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }
};
