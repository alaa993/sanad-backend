
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('journals', function(Blueprint $t){
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('entry'); $t->timestamp('created_at')->useCurrent();
        });
    } public function down(): void { Schema::dropIfExists('journals'); }
};
