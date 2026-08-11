<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organization_user', function (Blueprint $table) {
            $table->index(['organization_id', 'role'], 'org_user_org_role');
            $table->index(['user_id', 'role'], 'org_user_user_role');
        });

        Schema::table('organization_beneficiaries', function (Blueprint $table) {
            $table->index(['organization_id', 'status'], 'org_benef_org_status');
            $table->index(['organization_id', 'risk_level'], 'org_benef_org_risk');
            $table->index(['assigned_specialist_id'], 'org_benef_assigned_spec');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['owner_type', 'owner_id', 'created_at'], 'tx_owner_created');
            $table->index(['type', 'created_at'], 'tx_type_created');
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->index(['community_id', 'created_at'], 'community_posts_feed');
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropIndex('community_posts_feed');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('tx_owner_created');
            $table->dropIndex('tx_type_created');
        });

        Schema::table('organization_beneficiaries', function (Blueprint $table) {
            $table->dropIndex('org_benef_org_status');
            $table->dropIndex('org_benef_org_risk');
            $table->dropIndex('org_benef_assigned_spec');
        });

        Schema::table('organization_user', function (Blueprint $table) {
            $table->dropIndex('org_user_org_role');
            $table->dropIndex('org_user_user_role');
        });
    }
};
