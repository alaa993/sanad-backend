<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'facebook_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('facebook_id', 128)->nullable()->unique()->after('apple_id');
            });
        }

        if (Schema::hasTable('communities') && !Schema::hasColumn('communities', 'organization_id')) {
            Schema::table('communities', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('owner_id');
                $table->index('organization_id');
            });
        }

        if (Schema::hasTable('patient_intakes') && !Schema::hasColumn('patient_intakes', 'referral_physician_recommended')) {
            Schema::table('patient_intakes', function (Blueprint $table) {
                $table->boolean('referral_physician_recommended')->default(false)->after('recommended_specialist_notes');
                $table->timestamp('referral_recommended_at')->nullable()->after('referral_physician_recommended');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('patient_intakes') && Schema::hasColumn('patient_intakes', 'referral_recommended_at')) {
            Schema::table('patient_intakes', function (Blueprint $table) {
                $table->dropColumn(['referral_physician_recommended', 'referral_recommended_at']);
            });
        }

        if (Schema::hasTable('communities') && Schema::hasColumn('communities', 'organization_id')) {
            Schema::table('communities', function (Blueprint $table) {
                $table->dropIndex(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'facebook_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('facebook_id');
            });
        }
    }
};
