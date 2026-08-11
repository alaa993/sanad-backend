
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('communities', function(Blueprint $t){
            $t->id(); $t->string('slug')->unique(); $t->json('name'); $t->json('about')->nullable();
            $t->enum('visibility',['public','private'])->default('public');
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete(); $t->timestamps();
        });
    } public function down(): void { Schema::dropIfExists('communities'); }
};
