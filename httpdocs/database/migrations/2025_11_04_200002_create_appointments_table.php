
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('appointments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('specialist_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('starts_at')->useCurrent();
            $t->timestamp('ends_at')->useCurrent();
            $t->enum('status', ['pending','accepted','rejected','canceled','completed'])->default('pending');
            $t->enum('source', ['patient','specialist','org'])->default('patient');
            $t->text('notes')->nullable();
            $t->string('join_url')->nullable();
            $t->timestamps();
            $t->index(['specialist_id','starts_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('appointments'); }
};
