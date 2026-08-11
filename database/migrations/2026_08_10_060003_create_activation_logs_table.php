<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('license_key', 64);
            $table->enum('action', ['activate', 'verify', 'deactivate', 'auto_expire', 'suspend', 'terminate', 'reactivate']);
            $table->enum('platform', ['desktop', 'hosting', 'server', 'android'])->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('fingerprint', 128)->nullable();
            $table->json('device_info')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['license_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_logs');
    }
};