<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('task'); // task | question | exercise
            $table->string('status')->default('open'); // open | completed
            $table->text('patient_answer')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('appointment_id')->references('id')->on('appointments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_tasks');
    }
};
