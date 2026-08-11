<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('community_posts')) return;
        Schema::table('community_posts', function (Blueprint $table) {
            if (!Schema::hasColumn('community_posts', 'type')) {
                $table->string('type', 32)->default('personal')->after('media_url');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('community_posts')) return;
        Schema::table('community_posts', function (Blueprint $table) {
            if (Schema::hasColumn('community_posts', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
