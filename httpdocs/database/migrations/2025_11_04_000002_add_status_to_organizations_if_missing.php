
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('organizations') && !Schema::hasColumn('organizations','status')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('status')->default('pending')->index();
            });
        }
    }
    public function down(): void {
        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations','status')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
