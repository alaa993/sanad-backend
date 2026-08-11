<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('appointments')) {
            return;
        }
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('appointments', 'rejection_by')) {
                $table->string('rejection_by', 20)->nullable()->after('rejection_reason');
            }
        });
    }

    public function down(): void {
        if (!Schema::hasTable('appointments')) {
            return;
        }
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'rejection_by')) {
                $table->dropColumn('rejection_by');
            }
            if (Schema::hasColumn('appointments', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });
    }
};
