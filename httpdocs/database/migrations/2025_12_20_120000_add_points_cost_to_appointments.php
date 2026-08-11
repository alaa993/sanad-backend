<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('appointments') && !Schema::hasColumn('appointments','points_cost')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->integer('points_cost')->default(0)->after('type');
            });
        }
    }

    public function down(): void
    {
        // الإبقاء على الحقل للتوافق
    }
};
