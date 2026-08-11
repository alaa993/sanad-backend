<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'security_question')) {
                $table->string('security_question')->nullable()->after('timezone');
            }
            if (!Schema::hasColumn('users', 'security_answer_hash')) {
                $table->string('security_answer_hash')->nullable()->after('security_question');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'security_answer_hash')) {
                $table->dropColumn('security_answer_hash');
            }
            if (Schema::hasColumn('users', 'security_question')) {
                $table->dropColumn('security_question');
            }
        });
    }
};
