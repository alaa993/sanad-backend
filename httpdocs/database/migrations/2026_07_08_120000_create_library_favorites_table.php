<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('library_favorites')) {
            return;
        }
        Schema::create('library_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_article_id')->constrained('library_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['library_article_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_favorites');
    }
};
