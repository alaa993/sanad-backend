<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'rating')) {
                $table->unsignedTinyInteger('rating')->nullable()->after('status');
            }
            if (!Schema::hasColumn('appointments', 'specialist_notes')) {
                $table->text('specialist_notes')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'rating')) {
                $table->dropColumn('rating');
            }
            if (Schema::hasColumn('appointments', 'specialist_notes')) {
                $table->dropColumn('specialist_notes');
            }
        });
    }
};
