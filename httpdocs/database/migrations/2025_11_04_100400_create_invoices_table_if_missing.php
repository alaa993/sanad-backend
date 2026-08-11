
<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if(!Schema::hasTable('invoices')){
      Schema::create('invoices', function(Blueprint $t){
        $t->id(); $t->string('owner_type'); $t->unsignedBigInteger('owner_id'); $t->integer('total')->default(0); $t->string('currency',8)->default('USD');
        $t->string('pdf_url')->nullable(); $t->string('status')->default('paid');
        $t->timestamps();
      });
    }
  }
  public function down(): void { /* keep */ }
};
