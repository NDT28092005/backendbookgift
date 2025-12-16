<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('status');
            $table->string('customer_phone')->nullable()->after('customer_name');
            $table->string('customer_province')->nullable()->after('customer_phone');
            $table->string('customer_district')->nullable()->after('customer_province');
            $table->string('customer_ward')->nullable()->after('customer_district');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'customer_phone',
                'customer_province',
                'customer_district',
                'customer_ward',
            ]);
        });
    }
};
