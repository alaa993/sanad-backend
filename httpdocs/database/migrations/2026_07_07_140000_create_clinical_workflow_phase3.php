<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                if (!Schema::hasColumn('appointments', 'original_specialist_id')) {
                    $table->unsignedBigInteger('original_specialist_id')->nullable()->after('specialist_id');
                }
                if (!Schema::hasColumn('appointments', 'transferred_at')) {
                    $table->timestamp('transferred_at')->nullable()->after('closed_at');
                }
                if (!Schema::hasColumn('appointments', 'transfer_reason')) {
                    $table->string('transfer_reason', 191)->nullable()->after('transferred_at');
                }
                if (!Schema::hasColumn('appointments', 'recurrence_series_id')) {
                    $table->unsignedBigInteger('recurrence_series_id')->nullable()->after('transfer_reason');
                }
                if (!Schema::hasColumn('appointments', 'occurrence_index')) {
                    $table->unsignedSmallInteger('occurrence_index')->nullable()->default(0)->after('recurrence_series_id');
                }
            });
        }

        if (!Schema::hasTable('appointment_transfers')) {
            Schema::create('appointment_transfers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('appointment_id');
                $table->unsignedBigInteger('from_specialist_id')->nullable();
                $table->unsignedBigInteger('to_specialist_id')->nullable();
                $table->string('reason', 191)->nullable();
                $table->timestamps();
                $table->index('appointment_id');
            });
        }

        if (!Schema::hasTable('appointment_recurrence_series')) {
            Schema::create('appointment_recurrence_series', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('patient_id');
                $table->unsignedBigInteger('specialist_id');
                $table->string('frequency', 32)->default('weekly');
                $table->unsignedTinyInteger('weekday')->nullable();
                $table->time('time_of_day')->nullable();
                $table->timestamp('starts_at');
                $table->unsignedSmallInteger('occurrence_count')->default(1);
                $table->timestamp('ends_at')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 32)->default('active');
                $table->timestamps();
                $table->index(['patient_id', 'specialist_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_recurrence_series');
        Schema::dropIfExists('appointment_transfers');
        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                foreach (['original_specialist_id', 'transferred_at', 'transfer_reason', 'recurrence_series_id', 'occurrence_index'] as $col) {
                    if (Schema::hasColumn('appointments', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
