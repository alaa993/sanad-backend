
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if(!Schema::hasTable('transactions')){
      Schema::create('transactions', function(Blueprint $t){
        $t->id(); $t->string('owner_type'); $t->unsignedBigInteger('owner_id');
        $t->string('type'); // charge|refund|point_credit|point_debit
        $t->integer('amount')->default(0); $t->string('currency',8)->default('USD');
        $t->json('meta')->nullable(); $t->string('status')->default('succeeded');
        $t->timestamps();
      });
    }
  }
  public function down(): void { /* keep */ }
};
