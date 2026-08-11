<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('rater_id');
            $table->unsignedBigInteger('ratee_id');
            $table->string('direction'); // patient_to_specialist | specialist_to_patient
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('appointment_id')->references('id')->on('appointments')->cascadeOnDelete();
            $table->foreign('rater_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('ratee_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_ratings');
    }
};
