
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('articles', function(Blueprint $t){
            $t->id(); $t->string('slug')->unique(); $t->json('title'); $t->json('body'); $t->json('tags')->nullable();
            $t->boolean('published')->default(true); $t->timestamps();
        });
    } public function down(): void { Schema::dropIfExists('articles'); }
};
