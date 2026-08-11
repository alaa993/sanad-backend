<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'duration_minutes')) {
                $table->integer('duration_minutes')->default(60);
            }
            if (!Schema::hasColumn('appointments', 'extended_minutes')) {
                $table->integer('extended_minutes')->default(0);
            }
            if (!Schema::hasColumn('appointments', 'closed_at')) {
                $table->timestamp('closed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'duration_minutes')) {
                $table->dropColumn('duration_minutes');
            }
            if (Schema::hasColumn('appointments', 'extended_minutes')) {
                $table->dropColumn('extended_minutes');
            }
            if (Schema::hasColumn('appointments', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
        });
    }
};
