<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();

                // Thông tin cơ bản
                $table->string('name', 100);
                $table->string('email', 100)->unique();
                $table->string('password', 255);
                $table->string('phone', 20)->nullable();
                $table->string('avatar', 255)->nullable();
                $table->string('role', 20)->default('customer');
                $table->boolean('is_active')->default(true);
                $table->string('google_id')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
