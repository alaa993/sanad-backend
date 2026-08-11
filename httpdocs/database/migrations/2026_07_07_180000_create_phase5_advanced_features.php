<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'gender')) {
                    $table->string('gender', 32)->nullable()->after('timezone');
                }
                if (!Schema::hasColumn('users', 'google_id')) {
                    $table->string('google_id', 128)->nullable()->unique()->after('gender');
                }
                if (!Schema::hasColumn('users', 'apple_id')) {
                    $table->string('apple_id', 128)->nullable()->unique()->after('google_id');
                }
            });
        }

        if (Schema::hasTable('group_sessions')) {
            Schema::table('group_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('group_sessions', 'max_capacity')) {
                    $table->unsignedSmallInteger('max_capacity')->default(20)->after('status');
                }
                if (!Schema::hasColumn('group_sessions', 'is_public')) {
                    $table->boolean('is_public')->default(false)->after('max_capacity');
                }
                if (!Schema::hasColumn('group_sessions', 'description')) {
                    $table->text('description')->nullable()->after('topic');
                }
            });
        }

        if (!Schema::hasTable('anonymous_match_requests')) {
            Schema::create('anonymous_match_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('gender', 32);
                $table->string('match_gender', 32)->default('any');
                $table->string('mode', 32)->default('chat');
                $table->string('status', 32)->default('waiting');
                $table->unsignedBigInteger('partner_id')->nullable();
                $table->unsignedBigInteger('chat_id')->nullable();
                $table->string('alias_self', 64)->nullable();
                $table->string('alias_partner', 64)->nullable();
                $table->timestamp('matched_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }

        if (!Schema::hasTable('anonymous_match_reports')) {
            Schema::create('anonymous_match_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('match_request_id')->constrained('anonymous_match_requests')->cascadeOnDelete();
                $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
                $table->string('reason', 500)->nullable();
                $table->string('status', 32)->default('open');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('coach_programs')) {
            Schema::create('coach_programs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('specialist_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('category', 32)->default('general');
                $table->string('title');
                $table->json('goals')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('coach_plan_items')) {
            Schema::create('coach_plan_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('coach_programs')->cascadeOnDelete();
                $table->string('kind', 32)->default('tip');
                $table->string('title');
                $table->string('schedule', 32)->nullable();
                $table->json('meta')->nullable();
                $table->boolean('is_done')->default(false);
                $table->timestamp('done_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('coach_checkins')) {
            Schema::create('coach_checkins', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('coach_programs')->cascadeOnDelete();
                $table->decimal('weight_kg', 5, 2)->nullable();
                $table->string('mood', 32)->nullable();
                $table->string('note', 1000)->nullable();
                $table->timestamp('logged_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_checkins');
        Schema::dropIfExists('coach_plan_items');
        Schema::dropIfExists('coach_programs');
        Schema::dropIfExists('anonymous_match_reports');
        Schema::dropIfExists('anonymous_match_requests');
        if (Schema::hasTable('group_sessions')) {
            Schema::table('group_sessions', function (Blueprint $table) {
                foreach (['max_capacity', 'is_public', 'description'] as $col) {
                    if (Schema::hasColumn('group_sessions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['gender', 'google_id', 'apple_id'] as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
