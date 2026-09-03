<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_installations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_id')->index();
            $table->unsignedBigInteger('license_activation_id')->nullable()->index();
            $table->string('install_id', 128)->unique();
            $table->string('fingerprint', 128)->nullable();
            $table->string('platform', 32);
            $table->string('domain', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('hostname', 255)->nullable();
            $table->json('server_info')->nullable();
            $table->string('transfer_token', 128)->nullable();
            $table->dateTime('transfer_token_expires_at')->nullable();
            $table->dateTime('bound_at')->nullable();
            $table->dateTime('last_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('license_id')
                ->references('id')
                ->on('licenses')
                ->cascadeOnDelete();

            $table->index(['license_id', 'is_active']);
            $table->index('transfer_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_installations');
    }
};
