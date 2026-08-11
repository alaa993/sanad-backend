<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('specialist_profiles')) {
            Schema::table('specialist_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('specialist_profiles', 'status')) {
                    $table->string('status')->default('pending')->index()->after('currency');
                }
                if (!Schema::hasColumn('specialist_profiles', 'verification_notes')) {
                    $table->text('verification_notes')->nullable()->after('status');
                }
            });
        }

        if (!Schema::hasTable('specialist_documents')) {
            Schema::create('specialist_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type')->comment('license, degree, id, etc.');
                $table->string('title')->nullable();
                $table->string('file_path');
                $table->json('meta')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('specialist_documents')) {
            Schema::dropIfExists('specialist_documents');
        }

        if (Schema::hasTable('specialist_profiles') && Schema::hasColumn('specialist_profiles', 'verification_notes')) {
            Schema::table('specialist_profiles', function (Blueprint $table) {
                $table->dropColumn('verification_notes');
            });
        }
    }
};
