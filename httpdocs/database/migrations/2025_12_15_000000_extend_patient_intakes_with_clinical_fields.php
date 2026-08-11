<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('patient_intakes', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('user_id');
            $table->string('severity_level')->nullable()->after('issue_duration');
            $table->string('impact_level')->nullable()->after('severity_level');
            $table->string('preferred_session_mode')->nullable()->after('impact_level');
            $table->json('risk_flags')->nullable()->after('preferred_session_mode');
            $table->foreignId('recommended_specialist_id')
                ->nullable()
                ->after('triage_recommendation')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('recommended_specialist_notes')->nullable()->after('recommended_specialist_id');
            $table->foreignId('initial_session_id')
                ->nullable()
                ->after('recommended_specialist_notes')
                ->constrained('therapy_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patient_intakes', function (Blueprint $table) {
            $table->dropForeign(['recommended_specialist_id']);
            $table->dropForeign(['initial_session_id']);
            $table->dropColumn([
                'full_name',
                'severity_level',
                'impact_level',
                'preferred_session_mode',
                'risk_flags',
                'recommended_specialist_id',
                'recommended_specialist_notes',
                'initial_session_id',
            ]);
        });
    }
};
