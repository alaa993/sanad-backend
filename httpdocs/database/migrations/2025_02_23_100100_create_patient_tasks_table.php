<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $hasAppointments = Schema::hasTable('appointments');

        Schema::create('patient_tasks', function (Blueprint $table) use ($hasAppointments) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('reminder_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['pending','completed','overdue'])->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['user_id','status']);

            if ($hasAppointments) {
                $table->foreign('appointment_id')
                    ->references('id')
                    ->on('appointments')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_tasks');
    }
};
