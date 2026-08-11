<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create roles
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $support = Role::firstOrCreate(['name' => 'support']);

        // Create permissions
        $permissions = [
            'view dashboard',
            'manage products',
            'manage licenses',
            'view logs',
            'manage settings',
            'manage users',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign all permissions to super-admin
        $superAdmin->syncPermissions($permissions);

        // Assign specific permissions to admin
        $admin->syncPermissions([
            'view dashboard',
            'manage products',
            'manage licenses',
            'view logs',
        ]);

        // Assign limited permissions to support
        $support->syncPermissions([
            'view dashboard',
            'view logs',
        ]);

        // Create default admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => 'password',
            ]
        );
        $adminUser->assignRole('super-admin');

        // Seed default settings
        $defaultSettings = [
            'verify_ttl_hours' => '24',
            'grace_period_days' => '7',
            'license_key_prefix' => 'SP-',
            'api_enabled' => '1',
            'api_key' => bin2hex(random_bytes(32)),
            'whmcs_enabled' => '0',
            'whmcs_url' => '',
            'whmcs_api_identifier' => '',
            'whmcs_api_secret' => '',
        ];

        foreach ($defaultSettings as $key => $value) {
            Setting::firstOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value]
            );
        }
    }
}