<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Product;
use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_ping_endpoint_is_public(): void
    {
        $response = $this->postJson('/api/v1/ping');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'pong',
            ]);
    }

    public function test_api_key_is_required(): void
    {
        $response = $this->postJson('/api/v1/activate', [
            'license_key' => 'SP-TEST',
            'fingerprint' => 'abc123',
            'platform' => 'desktop',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'code' => 401,
            ]);
    }

    public function test_invalid_api_key_is_rejected(): void
    {
        $response = $this->withHeader('X-API-Key', 'wrong-key')
            ->postJson('/api/v1/activate', [
                'license_key' => 'SP-TEST',
                'fingerprint' => 'abc123',
                'platform' => 'desktop',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'code' => 403,
            ]);
    }

    public function test_valid_api_key_passes(): void
    {
        $apiKey = Setting::get('api_key');

        $response = $this->withHeader('X-API-Key', $apiKey)
            ->postJson('/api/v1/activate', [
                'license_key' => 'SP-TEST',
                'fingerprint' => 'abc123',
                'platform' => 'desktop',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'code' => 404,
            ]);
    }
}