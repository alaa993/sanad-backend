
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('specialist_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('specialty')->nullable();
            $t->json('languages')->nullable();
            $t->unsignedSmallInteger('years_exp')->default(0);
            $t->boolean('accepting_new')->default(true);
            $t->json('bio')->nullable();
            $t->unsignedInteger('rate_cents')->default(0);
            $t->string('currency', 3)->default('USD');
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('specialist_profiles'); }
};
