<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vent_posts') && !Schema::hasColumn('vent_posts', 'hidden_at')) {
            Schema::table('vent_posts', function (Blueprint $table) {
                $table->timestamp('hidden_at')->nullable()->after('body');
            });
        }

        if (!Schema::hasTable('vent_reactions')) {
            Schema::create('vent_reactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vent_post_id')->constrained('vent_posts')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 32);
                $table->timestamps();
                $table->unique(['vent_post_id', 'user_id', 'type']);
            });
        }

        if (!Schema::hasTable('vent_reports')) {
            Schema::create('vent_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vent_post_id')->constrained('vent_posts')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reason', 500)->nullable();
                $table->string('status', 32)->default('open');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('daily_tips')) {
            Schema::create('daily_tips', function (Blueprint $table) {
                $table->id();
                $table->date('tip_date')->unique();
                $table->json('title');
                $table->json('body')->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('library_articles')) {
            Schema::table('library_articles', function (Blueprint $table) {
                if (!Schema::hasColumn('library_articles', 'video_url')) {
                    $table->string('video_url', 2048)->nullable()->after('image');
                }
                if (!Schema::hasColumn('library_articles', 'thumbnail')) {
                    $table->string('thumbnail', 2048)->nullable()->after('video_url');
                }
                if (!Schema::hasColumn('library_articles', 'tags')) {
                    $table->json('tags')->nullable()->after('duration');
                }
            });
        }

        if (Schema::hasTable('communities') && !Schema::hasColumn('communities', 'kind')) {
            Schema::table('communities', function (Blueprint $table) {
                $table->string('kind', 32)->default('discussion')->after('visibility');
            });
        }

        if (Schema::hasTable('community_posts')) {
            Schema::table('community_posts', function (Blueprint $table) {
                if (!Schema::hasColumn('community_posts', 'post_kind')) {
                    $table->string('post_kind', 32)->default('post')->after('type');
                }
                if (!Schema::hasColumn('community_posts', 'question_id')) {
                    $table->unsignedBigInteger('question_id')->nullable()->after('post_kind');
                }
                if (!Schema::hasColumn('community_posts', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable()->after('question_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vent_reports');
        Schema::dropIfExists('vent_reactions');
        Schema::dropIfExists('daily_tips');
        if (Schema::hasTable('vent_posts') && Schema::hasColumn('vent_posts', 'hidden_at')) {
            Schema::table('vent_posts', function (Blueprint $table) {
                $table->dropColumn('hidden_at');
            });
        }
        if (Schema::hasTable('library_articles')) {
            Schema::table('library_articles', function (Blueprint $table) {
                foreach (['video_url', 'thumbnail', 'tags'] as $col) {
                    if (Schema::hasColumn('library_articles', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('communities') && Schema::hasColumn('communities', 'kind')) {
            Schema::table('communities', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
        if (Schema::hasTable('community_posts')) {
            Schema::table('community_posts', function (Blueprint $table) {
                foreach (['post_kind', 'question_id', 'accepted_at'] as $col) {
                    if (Schema::hasColumn('community_posts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
