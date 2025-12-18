<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_anniversaries', function (Blueprint $table) {
            $table->date('reminder_15_days_sent_at')->nullable()->after('event_date');
            $table->date('reminder_10_days_sent_at')->nullable()->after('reminder_15_days_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_anniversaries', function (Blueprint $table) {
            $table->dropColumn(['reminder_15_days_sent_at', 'reminder_10_days_sent_at']);
        });
    }
};
