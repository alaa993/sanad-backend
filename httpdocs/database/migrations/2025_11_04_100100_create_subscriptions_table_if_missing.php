
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if(!Schema::hasTable('subscriptions')){
      Schema::create('subscriptions', function(Blueprint $t){
        $t->id(); $t->unsignedBigInteger('user_id')->nullable(); $t->unsignedBigInteger('organization_id')->nullable();
        $t->unsignedBigInteger('plan_id'); $t->string('status')->default('active');
        $t->timestamp('period_start')->nullable(); $t->timestamp('period_end')->nullable();
        $t->string('external_ref')->nullable();
        $t->timestamps();
      });
    }
  }
  public function down(): void { /* keep */ }
};
