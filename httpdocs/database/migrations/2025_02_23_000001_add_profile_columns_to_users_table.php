<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('patient')->after('password');
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('avatar');
            }
            if (!Schema::hasColumn('users', 'locale')) {
                $table->string('locale', 5)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone')->nullable()->after('locale');
            }
            if (!Schema::hasColumn('users', 'points')) {
                $table->unsignedInteger('points')->default(0)->after('timezone');
            }
            if (!Schema::hasColumn('users', 'extra')) {
                $table->json('extra')->nullable()->after('points');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = ['role','avatar','phone','locale','timezone','points','extra'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
