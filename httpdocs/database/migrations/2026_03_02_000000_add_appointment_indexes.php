<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['specialist_id', 'status', 'starts_at'], 'appointments_specialist_status_starts');
            $table->index(['patient_id', 'status', 'starts_at'], 'appointments_patient_status_starts');
            $table->index(['organization_id', 'status', 'starts_at'], 'appointments_org_status_starts');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_specialist_status_starts');
            $table->dropIndex('appointments_patient_status_starts');
            $table->dropIndex('appointments_org_status_starts');
        });
    }
};
