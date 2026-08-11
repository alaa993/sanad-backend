<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('community_posts') && !Schema::hasColumn('community_posts', 'media_url')) {
            Schema::table('community_posts', function (Blueprint $table) {
                $table->string('media_url', 2048)->nullable()->after('body');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('community_posts') && Schema::hasColumn('community_posts', 'media_url')) {
            Schema::table('community_posts', function (Blueprint $table) {
                $table->dropColumn('media_url');
            });
        }
    }
};
