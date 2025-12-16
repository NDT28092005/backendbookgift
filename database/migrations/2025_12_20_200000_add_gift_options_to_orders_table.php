<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'wrapping_paper_id')) {
                $table->foreignId('wrapping_paper_id')->nullable()->after('customer_ward')->constrained('wrapping_papers')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'wrapping_paper')) {
                $table->string('wrapping_paper')->nullable()->after('wrapping_paper_id');
            }

            if (!Schema::hasColumn('orders', 'decorative_accessory_id')) {
                $table->foreignId('decorative_accessory_id')->nullable()->after('wrapping_paper')->constrained('decorative_accessories')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'decorative_accessories')) {
                $table->string('decorative_accessories')->nullable()->after('decorative_accessory_id');
            }

            if (!Schema::hasColumn('orders', 'card_type_id')) {
                $table->foreignId('card_type_id')->nullable()->after('decorative_accessories')->constrained('card_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'card_type')) {
                $table->string('card_type')->nullable()->after('card_type_id');
            }

            if (!Schema::hasColumn('orders', 'card_note')) {
                $table->text('card_note')->nullable()->after('card_type');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'card_note')) {
                $table->dropColumn('card_note');
            }

            if (Schema::hasColumn('orders', 'card_type_id')) {
                $table->dropForeign(['card_type_id']);
                $table->dropColumn('card_type_id');
            }
            if (Schema::hasColumn('orders', 'card_type')) {
                $table->dropColumn('card_type');
            }

            if (Schema::hasColumn('orders', 'decorative_accessory_id')) {
                $table->dropForeign(['decorative_accessory_id']);
                $table->dropColumn('decorative_accessory_id');
            }
            if (Schema::hasColumn('orders', 'decorative_accessories')) {
                $table->dropColumn('decorative_accessories');
            }

            if (Schema::hasColumn('orders', 'wrapping_paper_id')) {
                $table->dropForeign(['wrapping_paper_id']);
                $table->dropColumn('wrapping_paper_id');
            }
            if (Schema::hasColumn('orders', 'wrapping_paper')) {
                $table->dropColumn('wrapping_paper');
            }
        });
    }
};

