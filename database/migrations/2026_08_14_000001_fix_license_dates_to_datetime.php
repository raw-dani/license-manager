<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dateTime('expires_at')->nullable()->change();
            $table->dateTime('activated_at')->nullable()->change();
            $table->dateTime('last_verified_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->change();
            $table->timestamp('activated_at')->nullable()->change();
            $table->timestamp('last_verified_at')->nullable()->change();
        });
    }
};
