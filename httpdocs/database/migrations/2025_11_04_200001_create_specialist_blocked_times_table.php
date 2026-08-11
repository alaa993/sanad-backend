
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('specialist_blocked_times', function (Blueprint $t) {
            $t->id();
            $t->foreignId('specialist_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('start_at')->useCurrent();
            $t->timestamp('end_at')->useCurrent();
            $t->string('reason')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('specialist_blocked_times'); }
};
