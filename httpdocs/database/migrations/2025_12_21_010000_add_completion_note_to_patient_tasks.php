<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('patient_tasks')) {
            return;
        }
        Schema::table('patient_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('patient_tasks', 'completion_note')) {
                $table->text('completion_note')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('patient_tasks')) {
            return;
        }
        Schema::table('patient_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('patient_tasks', 'completion_note')) {
                $table->dropColumn('completion_note');
            }
        });
    }
};
