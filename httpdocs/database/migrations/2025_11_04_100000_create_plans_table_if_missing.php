
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if(!Schema::hasTable('plans')){
      Schema::create('plans', function(Blueprint $t){
        $t->id(); $t->string('slug')->unique(); $t->string('type')->default('patient'); // patient|org
        $t->string('cycle')->default('monthly'); $t->integer('price')->default(0); $t->string('currency',8)->default('USD');
        $t->json('features')->nullable(); $t->boolean('is_active')->default(true);
        $t->timestamps();
      });
    }
  }
  public function down(): void { /* keep */ }
};
