
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if(!Schema::hasTable('report_snapshots')){
      Schema::create('report_snapshots', function(Blueprint $t){
        $t->id();
        $t->string('scope')->default('global'); // global|organization|specialist
        $t->unsignedBigInteger('scope_id')->nullable();
        $t->date('day');
        $t->integer('sessions_total')->default(0);
        $t->integer('sessions_paid')->default(0);
        $t->integer('new_users')->default(0);
        $t->integer('revenue')->default(0);
        $t->float('avg_rating')->nullable();
        $t->timestamps();
      });
    }
  }
  public function down(): void { /* keep */ }
};
