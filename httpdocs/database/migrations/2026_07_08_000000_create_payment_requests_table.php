<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('payment_requests')) {
            return;
        }

        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 40)->default('mtn');
            $table->string('purpose', 40)->default('wallet_topup');
            $table->unsignedInteger('amount');
            $table->string('currency', 8)->default('SYP');
            $table->string('reference', 80)->unique();
            $table->string('external_ref', 120)->nullable();
            $table->string('phone', 24)->nullable();
            $table->string('status', 24)->default('pending');
            $table->json('meta')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
