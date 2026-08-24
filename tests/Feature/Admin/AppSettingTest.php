<?php

namespace Tests\Feature\Admin;

use App\Models\AppConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/settings', [
            'late_grace_period_minutes' => 20,
            'default_shift_start' => '08:30',
            'app_latest_version' => '1.2.0',
            'app_min_version' => '1.0.0',
            'app_force_update' => true,
            'app_download_url' => 'https://example.com/app.apk',
            'app_changelog' => 'Bug fixes and performance improvements.',
        ]);

        $response->assertRedirect();
        $this->assertEquals('20', AppConfig::where('key', 'late_grace_period_minutes')->value('value'));
        $this->assertEquals('08:30', AppConfig::where('key', 'default_shift_start')->value('value'));
        $this->assertEquals('1.2.0', AppConfig::where('key', 'app_latest_version')->value('value'));
        $this->assertEquals('true', AppConfig::where('key', 'app_force_update')->value('value'));
    }

    public function test_public_app_version_check_endpoint(): void
    {
        AppConfig::create(['key' => 'app_latest_version', 'value' => '1.5.0']);
        AppConfig::create(['key' => 'app_min_version', 'value' => '1.2.0']);
        AppConfig::create(['key' => 'app_force_update', 'value' => 'false']);
        AppConfig::create(['key' => 'app_download_url', 'value' => 'https://example.com/latest.apk']);
        AppConfig::create(['key' => 'app_changelog', 'value' => '• New UI features.']);

        // Case 1: App on 1.0.0 (below min_version 1.2.0 -> forced)
        $response = $this->getJson('/api/v1/app/version-check?current_version=1.0.0');
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'has_update' => true,
                'is_forced' => true,
                'latest_version' => '1.5.0',
                'download_url' => 'https://example.com/latest.apk',
            ]);

        // Case 2: App on 1.3.0 (above min_version 1.2.0, force_update is false -> optional)
        $response2 = $this->getJson('/api/v1/app/version-check?current_version=1.3.0');
        $response2->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'has_update' => true,
                'is_forced' => false,
                'latest_version' => '1.5.0',
            ]);

        // Case 3: App already on 1.5.0 (latest -> no update)
        $response3 = $this->getJson('/api/v1/app/version-check?current_version=1.5.0');
        $response3->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'has_update' => false,
                'is_forced' => false,
            ]);
    }
}
