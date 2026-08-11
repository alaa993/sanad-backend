<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organization_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('assigned_specialist_id')->nullable();
            $table->string('status')->default('active'); // active|inactive|awaiting
            $table->string('risk_level')->nullable(); // low|medium|high
            $table->string('primary_issue')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_session_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_specialist_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['organization_id','patient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_beneficiaries');
    }
};
