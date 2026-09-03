<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE activation_logs MODIFY COLUMN action ENUM('activate', 'verify', 'deactivate', 'auto_expire', 'suspend', 'terminate', 'reactivate', 'bind', 'transfer_token') NOT NULL");
        DB::statement("ALTER TABLE activation_logs MODIFY COLUMN platform VARCHAR(32) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE activation_logs MODIFY COLUMN action ENUM('activate', 'verify', 'deactivate', 'auto_expire', 'suspend', 'terminate', 'reactivate') NOT NULL");
        DB::statement("ALTER TABLE activation_logs MODIFY COLUMN platform ENUM('desktop', 'hosting', 'server', 'android') NULL");
    }
};
