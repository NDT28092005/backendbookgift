<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('carts')) {
            return;
        }

        // Gộp các cart item trùng lặp (cùng user + product)
        $duplicates = DB::table('carts')
            ->select('user_id', 'product_id', DB::raw('COUNT(*) as dup_count'), DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('user_id', 'product_id')
            ->having('dup_count', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $rows = DB::table('carts')
                ->where('user_id', $dup->user_id)
                ->where('product_id', $dup->product_id)
                ->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $firstId = $rows->first()->id;
            DB::table('carts')
                ->where('id', $firstId)
                ->update(['quantity' => $dup->total_quantity]);

            $duplicateIds = $rows->pluck('id')->filter(fn ($id) => $id !== $firstId);
            if ($duplicateIds->isNotEmpty()) {
                DB::table('carts')->whereIn('id', $duplicateIds)->delete();
            }
        }

        Schema::table('carts', function (Blueprint $table) {
            $table->unique(['user_id', 'product_id'], 'carts_user_product_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('carts')) {
            return;
        }

        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique('carts_user_product_unique');
        });
    }
};

