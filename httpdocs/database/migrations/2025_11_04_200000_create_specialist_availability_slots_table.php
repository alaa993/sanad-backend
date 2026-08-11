
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('specialist_availability_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('specialist_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedTinyInteger('weekday'); // 0=Sun..6=Sat
            $t->time('start_time');
            $t->time('end_time');
            $t->string('repeat_rule')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('specialist_availability_slots'); }
};
