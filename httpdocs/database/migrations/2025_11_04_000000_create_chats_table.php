<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('chats', function(Blueprint $t){
            $t->id();
            $t->string('subject')->nullable();
            $t->text('last_message')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('chats'); }
};
