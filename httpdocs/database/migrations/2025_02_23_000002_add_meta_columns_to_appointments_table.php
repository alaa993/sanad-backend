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
            if (!Schema::hasColumn('appointments', 'type')) {
                $table->string('type')->default('video')->after('specialist_id');
            }
            if (!Schema::hasColumn('appointments', 'organization_id')) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('type')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('appointments', 'join_url')) {
                $table->string('join_url')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('appointments', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('ends_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('appointments')) {
            return;
        }
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'join_url')) {
                $table->dropColumn('join_url');
            }
            if (Schema::hasColumn('appointments', 'scheduled_at')) {
                $table->dropColumn('scheduled_at');
            }
            if (Schema::hasColumn('appointments', 'organization_id')) {
                $table->dropConstrainedForeignId('organization_id');
            }
            if (Schema::hasColumn('appointments', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
