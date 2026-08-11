<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('group_session_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_session_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('user');
            $table->dateTime('joined_at')->nullable();
            $table->dateTime('left_at')->nullable();
            $table->timestamps();

            $table->unique(['group_session_id', 'user_id']);
            $table->foreign('group_session_id')->references('id')->on('group_sessions')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('group_session_participants');
    }
};
