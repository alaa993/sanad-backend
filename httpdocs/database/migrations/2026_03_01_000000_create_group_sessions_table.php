<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('group_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('topic')->nullable();
            $table->string('type')->default('chat');
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->unsignedBigInteger('specialist_id')->nullable();
            $table->unsignedBigInteger('chat_id')->nullable();
            $table->string('join_url')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('specialist_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('chat_id')->references('id')->on('chats')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('group_sessions');
    }
};
