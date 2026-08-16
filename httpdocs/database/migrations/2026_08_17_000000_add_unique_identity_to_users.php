<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'name')) {
                $table->unique('name', 'users_name_unique');
            }
            if (Schema::hasColumn('users', 'phone')) {
                $table->unique('phone', 'users_phone_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            try {
                $table->dropUnique('users_name_unique');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropUnique('users_phone_unique');
            } catch (\Throwable $e) {
            }
        });
    }
};
