<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('chat_participants', function(Blueprint $t){
            $t->id();
            $t->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('role',['user','specialist','support'])->default('user');
            $t->timestamp('joined_at')->nullable();
            $t->timestamp('left_at')->nullable();
            $t->timestamps();
            $t->unique(['chat_id','user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('chat_participants'); }
};
