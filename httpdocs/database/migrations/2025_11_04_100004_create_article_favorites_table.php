
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('article_favorites', function(Blueprint $t){
            $t->id(); $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps(); $t->unique(['article_id','user_id']);
        });
    } public function down(): void { Schema::dropIfExists('article_favorites'); }
};
