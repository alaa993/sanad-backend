<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('messages', function(Blueprint $t){
            $t->id();
            $t->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $t->enum('type',['text','image'])->default('text');
            $t->text('body');
            $t->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void { Schema::dropIfExists('messages'); }
};
