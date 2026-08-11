<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_articles', function (Blueprint $table) {
            if (!Schema::hasColumn('library_articles', 'author_name')) {
                $table->string('author_name')->nullable()->after('duration');
            }
            if (!Schema::hasColumn('library_articles', 'author_title')) {
                $table->string('author_title')->nullable()->after('author_name');
            }
            if (!Schema::hasColumn('library_articles', 'author_avatar')) {
                $table->string('author_avatar')->nullable()->after('author_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('library_articles', function (Blueprint $table) {
            if (Schema::hasColumn('library_articles', 'author_avatar')) {
                $table->dropColumn('author_avatar');
            }
            if (Schema::hasColumn('library_articles', 'author_title')) {
                $table->dropColumn('author_title');
            }
            if (Schema::hasColumn('library_articles', 'author_name')) {
                $table->dropColumn('author_name');
            }
        });
    }
};
