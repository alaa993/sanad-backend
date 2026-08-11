
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('about')->nullable();
            $t->timestamps();
        });
        Schema::create('organization_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('role')->default('specialist'); // specialist|manager
            $t->timestamps();
            $t->unique(['organization_id','user_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
    }
};
