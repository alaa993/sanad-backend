<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('patient_intakes')) {
            Schema::table('patient_intakes', function (Blueprint $table) {
                if (!Schema::hasColumn('patient_intakes', 'onboarding_step')) {
                    $table->string('onboarding_step', 40)->nullable()->after('notes');
                }
                if (!Schema::hasColumn('patient_intakes', 'recovery_unlocked')) {
                    $table->boolean('recovery_unlocked')->default(false)->after('onboarding_step');
                }
                if (!Schema::hasColumn('patient_intakes', 'pre_session_survey')) {
                    $table->json('pre_session_survey')->nullable()->after('recovery_unlocked');
                }
                if (!Schema::hasColumn('patient_intakes', 'pre_session_completed_at')) {
                    $table->timestamp('pre_session_completed_at')->nullable()->after('pre_session_survey');
                }
                if (!Schema::hasColumn('patient_intakes', 'external_physician_recommended')) {
                    $table->boolean('external_physician_recommended')->default(false)->after('referral_recommended_at');
                }
                if (!Schema::hasColumn('patient_intakes', 'external_physician_acknowledged_at')) {
                    $table->timestamp('external_physician_acknowledged_at')->nullable()->after('external_physician_recommended');
                }
                if (!Schema::hasColumn('patient_intakes', 'external_physician_notes')) {
                    $table->text('external_physician_notes')->nullable()->after('external_physician_acknowledged_at');
                }
            });
        }

        if (Schema::hasTable('communities') && !Schema::hasColumn('communities', 'category')) {
            Schema::table('communities', function (Blueprint $table) {
                $table->string('category', 60)->nullable()->after('kind');
            });
        }

        if (Schema::hasTable('group_sessions')) {
            Schema::table('group_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('group_sessions', 'age_category')) {
                    $table->string('age_category', 40)->nullable()->after('topic');
                }
                if (!Schema::hasColumn('group_sessions', 'disorder_tag')) {
                    $table->string('disorder_tag', 40)->nullable()->after('age_category');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('patient_intakes')) {
            Schema::table('patient_intakes', function (Blueprint $table) {
                foreach ([
                    'onboarding_step', 'recovery_unlocked', 'pre_session_survey',
                    'pre_session_completed_at', 'external_physician_recommended',
                    'external_physician_acknowledged_at', 'external_physician_notes',
                ] as $col) {
                    if (Schema::hasColumn('patient_intakes', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('communities') && Schema::hasColumn('communities', 'category')) {
            Schema::table('communities', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
        if (Schema::hasTable('group_sessions')) {
            Schema::table('group_sessions', function (Blueprint $table) {
                foreach (['age_category', 'disorder_tag'] as $col) {
                    if (Schema::hasColumn('group_sessions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
