<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patient_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('occupation')->nullable();
            $table->string('issue_duration')->nullable(); // مثل less_3m / more_3m / more_year
            $table->json('symptoms')->nullable();         // قائمة الأعراض المختارة
            $table->string('primary_issue')->nullable();
            $table->unsignedTinyInteger('benefit_score')->nullable(); // نسبة متوقعة 0-100
            $table->boolean('previous_consult')->nullable();
            $table->text('consult_notes')->nullable();
            $table->text('notes')->nullable();
            $table->string('triage_category')->nullable();
            $table->json('triage_recommendation')->nullable(); // معلومات الأخصائي المقترح/السبب
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_intakes');
    }
};
