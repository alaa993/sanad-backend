
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if(!Schema::hasTable('coupons')){
      Schema::create('coupons', function(Blueprint $t){
        $t->id(); $t->string('code')->unique(); $t->integer('percent_off')->nullable(); $t->integer('amount_off')->nullable();
        $t->timestamp('expires_at')->nullable(); $t->integer('usage_limit')->nullable(); $t->timestamps();
      });
    }
  }
  public function down(): void { /* keep */ }
};
