
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if(!Schema::hasTable('wallets')){
      Schema::create('wallets', function(Blueprint $t){
        $t->id(); $t->string('owner_type'); $t->unsignedBigInteger('owner_id'); $t->integer('balance')->default(0); $t->integer('points')->default(0);
        $t->timestamps();
      });
    }
  }
  public function down(): void { /* keep */ }
};
