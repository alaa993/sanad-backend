<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('articles')) {
            return;
        }
        Schema::table('articles', function (Blueprint $table) {
            if (!Schema::hasColumn('articles', 'author_id')) {
                $table->unsignedBigInteger('author_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('articles', 'author_role')) {
                $table->string('author_role', 32)->nullable()->after('author_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('articles')) {
            return;
        }
        Schema::table('articles', function (Blueprint $table) {
            if (Schema::hasColumn('articles', 'author_role')) {
                $table->dropColumn('author_role');
            }
            if (Schema::hasColumn('articles', 'author_id')) {
                $table->dropColumn('author_id');
            }
        });
    }
};
