
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('community_members', function(Blueprint $t){
            $t->id(); $t->foreignId('community_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('role',['member','moderator','owner'])->default('member');
            $t->timestamps(); $t->unique(['community_id','user_id']);
        });
    } public function down(): void { Schema::dropIfExists('community_members'); }
};
