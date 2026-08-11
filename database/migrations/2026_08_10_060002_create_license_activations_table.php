<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 128);
            $table->enum('platform', ['desktop', 'hosting', 'server', 'android']);
            $table->json('device_info')->nullable();
            $table->string('domain')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            // Unique fingerprint per license (same device can't activate same license twice)
            $table->unique(['license_id', 'fingerprint']);
            $table->index('platform');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activations');
    }
};