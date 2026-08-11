<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_categories', function (Blueprint $table) {
            $table->id();
            $table->json('title'); // {"ar": "...", "en": "...", "tr": "..."}
            $table->timestamps();
        });

        Schema::create('library_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('library_categories')->cascadeOnDelete();
            $table->json('title');
            $table->json('body')->nullable();
            $table->string('image')->nullable();
            $table->string('type')->default('article'); // article | video
            $table->string('duration')->nullable();     // "5 min"
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_articles');
        Schema::dropIfExists('library_categories');
    }
};
