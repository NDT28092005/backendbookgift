<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Thêm image_url vào wrapping_papers
        if (Schema::hasTable('wrapping_papers')) {
            Schema::table('wrapping_papers', function (Blueprint $table) {
                if (!Schema::hasColumn('wrapping_papers', 'image_url')) {
                    $table->string('image_url')->nullable()->after('description');
                }
            });
        }

        // Thêm image_url vào decorative_accessories
        if (Schema::hasTable('decorative_accessories')) {
            Schema::table('decorative_accessories', function (Blueprint $table) {
                if (!Schema::hasColumn('decorative_accessories', 'image_url')) {
                    $table->string('image_url')->nullable()->after('description');
                }
            });
        }

        // Thêm image_url vào card_types
        if (Schema::hasTable('card_types')) {
            Schema::table('card_types', function (Blueprint $table) {
                if (!Schema::hasColumn('card_types', 'image_url')) {
                    $table->string('image_url')->nullable()->after('description');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('card_types') && Schema::hasColumn('card_types', 'image_url')) {
            Schema::table('card_types', function (Blueprint $table) {
                $table->dropColumn('image_url');
            });
        }

        if (Schema::hasTable('decorative_accessories') && Schema::hasColumn('decorative_accessories', 'image_url')) {
            Schema::table('decorative_accessories', function (Blueprint $table) {
                $table->dropColumn('image_url');
            });
        }

        if (Schema::hasTable('wrapping_papers') && Schema::hasColumn('wrapping_papers', 'image_url')) {
            Schema::table('wrapping_papers', function (Blueprint $table) {
                $table->dropColumn('image_url');
            });
        }
    }
};

