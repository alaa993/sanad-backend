<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('organizations')) return;
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'status')) {
                $table->string('status', 32)->default('pending')->after('about');
            }
        });
        if (Schema::hasTable('organizations')) {
            \Illuminate\Support\Facades\DB::table('organizations')
                ->whereNull('status')
                ->update(['status' => 'pending']);
            \Illuminate\Support\Facades\DB::table('organizations')
                ->where('status', 'active')
                ->update(['status' => 'approved']);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('organizations')) return;
        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
