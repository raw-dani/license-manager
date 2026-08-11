<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Product;
use App\Models\Setting;
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
}