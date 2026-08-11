
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('community_posts', function(Blueprint $t){
            $t->id(); $t->foreignId('community_id')->constrained()->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $t->text('body'); 
            $t->string('media_url', 2048)->nullable();
            $t->timestamps();
        });
    } public function down(): void { Schema::dropIfExists('community_posts'); }
};
