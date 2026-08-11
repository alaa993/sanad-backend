<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('coupons')) {
            Schema::table('coupons', function (Blueprint $table) {
                if (!Schema::hasColumn('coupons', 'points')) {
                    $table->integer('points')->default(0)->after('amount_off');
                }
                if (!Schema::hasColumn('coupons', 'type')) {
                    $table->string('type')->default('points')->after('points');
                }
                if (!Schema::hasColumn('coupons', 'used_by_type')) {
                    $table->string('used_by_type')->nullable()->after('usage_limit');
                }
                if (!Schema::hasColumn('coupons', 'used_by_id')) {
                    $table->unsignedBigInteger('used_by_id')->nullable()->after('used_by_type');
                }
                if (!Schema::hasColumn('coupons', 'used_at')) {
                    $table->timestamp('used_at')->nullable()->after('used_by_id');
                }
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('transactions', 'points')) {
                    $table->integer('points')->default(0)->after('amount');
                }
            });
        }
    }

    public function down(): void
    {
        // الحقول الجديدة تبقى لدعم التوافق، لا إسقاط تلقائي
    }
};
