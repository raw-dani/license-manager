<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseActivationTest extends TestCase
{
    use RefreshDatabase;

    protected string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->apiKey = Setting::get('api_key');
    }

    private function createLicense(array $overrides = []): License
    {
        $product = Product::create([
            'name' => 'Test App',
            'slug' => 'test-app',
            'platform' => 'desktop',
            'status' => true,
        ]);

        return License::create(array_merge([
            'license_key' => 'SP-' . strtoupper(bin2hex(random_bytes(3))),
            'product_id' => $product->id,
            'status' => 'active',
            'max_activations' => 2,
            'current_activations' => 0,
        ], $overrides));
    }

    public function test_can_activate_a_license(): void
    {
        $license = $this->createLicense();

        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/activate', [
                'license_key' => $license->license_key,
                'fingerprint' => str_repeat('a', 128),
                'platform' => 'desktop',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'License activated successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'license_key',
                    'token',
                    'expires_in',
                    'expires_at',
                ],
            ]);

        $this->assertDatabaseHas('license_activations', [
            'license_id' => $license->id,
            'fingerprint' => str_repeat('a', 128),
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'current_activations' => 1,
        ]);
    }

    public function test_can_verify_a_license(): void
    {
        $license = $this->createLicense();
        $fingerprint = str_repeat('b', 128);

        // First activate
        $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/activate', [
                'license_key' => $license->license_key,
                'fingerprint' => $fingerprint,
                'platform' => 'desktop',
            ]);

        // Then verify
        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/verify', [
                'license_key' => $license->license_key,
                'fingerprint' => $fingerprint,
                'platform' => 'desktop',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'License verified',
            ]);
    }

    public function test_can_deactivate_a_license(): void
    {
        $license = $this->createLicense();
        $fingerprint = str_repeat('c', 128);

        $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/activate', [
                'license_key' => $license->license_key,
                'fingerprint' => $fingerprint,
                'platform' => 'desktop',
            ]);

        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/deactivate', [
                'license_key' => $license->license_key,
                'fingerprint' => $fingerprint,
                'platform' => 'desktop',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Deactivated',
            ]);

        $this->assertDatabaseHas('license_activations', [
            'license_id' => $license->id,
            'fingerprint' => $fingerprint,
            'status' => 'inactive',
        ]);
    }

    public function test_license_not_found_returns_404(): void
    {
        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/activate', [
                'license_key' => 'SP-NOTEXIST',
                'fingerprint' => str_repeat('d', 128),
                'platform' => 'desktop',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'code' => 404,
                'message' => 'License not found',
            ]);
    }

    public function test_suspended_license_cannot_activate(): void
    {
        $license = $this->createLicense(['status' => 'suspended']);

        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/activate', [
                'license_key' => $license->license_key,
                'fingerprint' => str_repeat('e', 128),
                'platform' => 'desktop',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'code' => 403,
                'message' => 'License is suspended',
            ]);
    }

    public function test_max_activations_reached(): void
    {
        $license = $this->createLicense(['max_activations' => 1]);

        // First activation
        $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/activate', [
                'license_key' => $license->license_key,
                'fingerprint' => str_repeat('f', 128),
                'platform' => 'desktop',
            ]);

        // Second activation on different device should fail
        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/activate', [
                'license_key' => $license->license_key,
                'fingerprint' => str_repeat('g', 128),
                'platform' => 'desktop',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'code' => 403,
                'message' => 'Max activations reached',
            ]);
    }

    public function test_admin_can_suspend_license_via_api(): void
    {
        $license = $this->createLicense();
        $admin = User::firstWhere('email', 'admin@example.com');
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/licenses/' . $license->license_key . '/suspend');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'code' => 200,
                'message' => 'License suspended successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'license_key',
                    'status',
                    'suspended_at',
                ],
            ]);

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'status' => 'suspended',
        ]);

        $this->assertDatabaseHas('activation_logs', [
            'license_id' => $license->id,
            'action' => 'suspend',
        ]);
    }

    public function test_admin_can_unsuspend_license_via_api(): void
    {
        $license = $this->createLicense(['status' => 'suspended', 'suspended_at' => now()]);
        $admin = User::firstWhere('email', 'admin@example.com');
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/licenses/' . $license->license_key . '/unsuspend');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'code' => 200,
                'message' => 'License unsuspended successfully',
            ]);

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'status' => 'active',
            'suspended_at' => null,
        ]);
    }

    public function test_non_admin_cannot_suspend_license_via_api(): void
    {
        $license = $this->createLicense();
        $support = User::firstWhere('email', 'admin@example.com');
        $support->assignRole('support');
        $token = $support->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/licenses/' . $license->license_key . '/suspend');

        $response->assertStatus(422);
    }

    public function test_unauthenticated_cannot_suspend_license_via_api(): void
    {
        $license = $this->createLicense();

        $response = $this->postJson('/api/v1/admin/licenses/' . $license->license_key . '/suspend');

        $response->assertStatus(401);
    }

    public function test_status_endpoint_returns_suspended_at(): void
    {
        $license = $this->createLicense([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->getJson('/api/v1/license/' . $license->license_key . '/status');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'license_key' => $license->license_key,
                    'status' => 'suspended',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'suspended_at',
                ],
            ]);
    }

    public function test_suspend_returns_webhook_sent_status(): void
    {
        $license = $this->createLicense([
            'webhook_url' => 'https://ecatalog.test/api/license/callback',
            'webhook_secret' => 'test-secret',
        ]);
        $admin = User::firstWhere('email', 'admin@example.com');
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/licenses/' . $license->license_key . '/suspend');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'license_key',
                    'status',
                    'suspended_at',
                    'webhook_sent',
                ],
            ]);
    }

    public function test_notify_endpoint_sends_webhook(): void
    {
        $license = $this->createLicense([
            'webhook_url' => 'https://ecatalog.test/api/license/callback',
            'webhook_secret' => 'test-secret',
        ]);
        $admin = User::firstWhere('email', 'admin@example.com');
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/licenses/' . $license->license_key . '/notify');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'license_key',
                    'event',
                    'webhook_sent',
                ],
            ]);
    }

    public function test_notify_returns_error_when_webhook_not_configured(): void
    {
        $license = $this->createLicense();
        $admin = User::firstWhere('email', 'admin@example.com');
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/licenses/' . $license->license_key . '/notify');

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Webhook URL or secret not configured for this license',
            ]);
    }

    public function test_license_binds_to_first_installation(): void
    {
        $license = $this->createLicense();

        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/verify', [
                'license_key' => $license->license_key,
                'fingerprint' => str_repeat('h', 128),
                'platform' => 'hosting',
                'device_info' => [
                    'install_id' => 'server-abc-123',
                ],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('license_installations', [
            'license_id' => $license->id,
            'install_id' => 'server-abc-123',
            'is_active' => true,
        ]);
    }

    public function test_different_install_id_is_rejected(): void
    {
        $license = $this->createLicense();
        $fingerprint = str_repeat('i', 128);

        $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/verify', [
                'license_key' => $license->license_key,
                'fingerprint' => $fingerprint,
                'platform' => 'hosting',
                'device_info' => ['install_id' => 'server-original'],
            ]);

        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/verify', [
                'license_key' => $license->license_key,
                'fingerprint' => $fingerprint,
                'platform' => 'hosting',
                'device_info' => ['install_id' => 'server-stolen'],
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'code' => 403,
            ]);
    }

    public function test_bind_without_transfer_token_fails_when_already_bound(): void
    {
        $license = $this->createLicense();

        $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/verify', [
                'license_key' => $license->license_key,
                'fingerprint' => str_repeat('j', 128),
                'platform' => 'hosting',
                'device_info' => ['install_id' => 'server-first'],
            ]);

        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/bind', [
                'license_key' => $license->license_key,
                'install_id' => 'server-second',
                'platform' => 'hosting',
                'fingerprint' => str_repeat('j', 128),
            ]);

        $response->assertStatus(403);
    }

    public function test_bind_with_valid_transfer_token_succeeds(): void
    {
        $license = $this->createLicense();
        $admin = User::firstWhere('email', 'admin@example.com');
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/verify', [
                'license_key' => $license->license_key,
                'fingerprint' => str_repeat('k', 128),
                'platform' => 'hosting',
                'device_info' => ['install_id' => 'server-old'],
            ]);

        $tokenResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/licenses/' . $license->license_key . '/transfer-token', [
                'ttl_hours' => 24,
            ]);

        $tokenResponse->assertStatus(200);
        $transferToken = $tokenResponse->json('data.transfer_token');

        $response = $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/bind', [
                'license_key' => $license->license_key,
                'install_id' => 'server-new',
                'transfer_token' => $transferToken,
                'platform' => 'hosting',
                'fingerprint' => str_repeat('k', 128),
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'License bound to this installation',
            ]);

        $this->assertDatabaseHas('license_installations', [
            'license_id' => $license->id,
            'install_id' => 'server-new',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('license_installations', [
            'license_id' => $license->id,
            'install_id' => 'server-old',
            'is_active' => false,
        ]);
    }

    public function test_transfer_token_invalidated_after_use(): void
    {
        $license = $this->createLicense();
        $admin = User::firstWhere('email', 'admin@example.com');
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/verify', [
                'license_key' => $license->license_key,
                'fingerprint' => str_repeat('l', 128),
                'platform' => 'hosting',
                'device_info' => ['install_id' => 'server-1'],
            ]);

        $tokenResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/licenses/' . $license->license_key . '/transfer-token');
        $transferToken = $tokenResponse->json('data.transfer_token');

        $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/bind', [
                'license_key' => $license->license_key,
                'install_id' => 'server-2',
                'transfer_token' => $transferToken,
                'platform' => 'hosting',
                'fingerprint' => str_repeat('l', 128),
            ])->assertStatus(200);

        $reuseResponse = $this->withHeader('X-API-Key', $this->apiKey)
            ->postJson('/api/v1/bind', [
                'license_key' => $license->license_key,
                'install_id' => 'server-3',
                'transfer_token' => $transferToken,
                'platform' => 'hosting',
                'fingerprint' => str_repeat('l', 128),
            ]);

        $reuseResponse->assertStatus(403);
    }

    public function test_transfer_token_ttl_validation(): void
    {
        $license = $this->createLicense();
        $admin = User::firstWhere('email', 'admin@example.com');
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/licenses/' . $license->license_key . '/transfer-token', [
                'ttl_hours' => 9999,
            ]);

        $response->assertStatus(422);
    }
}
