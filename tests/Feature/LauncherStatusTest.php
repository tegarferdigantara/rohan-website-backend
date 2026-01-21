<?php

namespace Tests\Feature;

use App\Models\Launcher\ServerSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LauncherStatusTest extends TestCase
{
    protected function getValidApiKey(): string
    {
        $secret = ServerSetting::getValue('launcher_secret', 'o6LDOB3E2Nv4mYPM');
        return hash_hmac('sha256', date('Y-m-d'), $secret);
    }

    public function test_status_returns_correct_maintenance_value(): void
    {
        $currentMode = ServerSetting::getValue('maintenance_mode', '0');
        $expectedMaintenance = $currentMode === '1';

        $response = $this->withHeaders([
            'X-API-Key' => $this->getValidApiKey(),
        ])->getJson('/api/launcher/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'maintenance' => $expectedMaintenance,
            ])
            ->assertJsonStructure([
                'success',
                'ip',
                'active_sessions',
                'max_allowed',
                'slots_available',
                'maintenance',
                'server_time',
            ]);
    }

    public function test_status_response_structure(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => $this->getValidApiKey(),
        ])->getJson('/api/launcher/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'ip',
                'active_sessions',
                'max_allowed',
                'slots_available',
                'maintenance',
                'server_time',
            ]);
    }

    public function test_status_fails_without_api_key(): void
    {
        $response = $this->getJson('/api/launcher/status');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'API_KEY_MISSING',
            ]);
    }

    public function test_status_fails_with_invalid_api_key(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'invalid-key-here',
        ])->getJson('/api/launcher/status');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 'API_KEY_INVALID',
            ]);
    }
}
