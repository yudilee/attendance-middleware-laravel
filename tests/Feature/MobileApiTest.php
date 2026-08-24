<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\ApiKey;
use App\Models\Employee;
use App\Models\Branch;
use App\Models\DeviceBinding;
use App\Models\PunchType;
use Carbon\Carbon;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_status_endpoint_returns_ok_without_auth(): void
    {
        $response = $this->getJson('/api/v1/app-status');

        $response->assertStatus(200)
                 ->assertJsonStructure(['status', 'min_app_version', 'latest_app_version', 'server_time']);
    }

    public function test_device_config_requires_valid_api_key(): void
    {
        // 1. Missing API Key
        $response = $this->getJson('/api/v1/device-config?device_uuid=test_uuid');
        $response->assertStatus(401);

        // 2. Invalid API Key
        $response = $this->withHeaders(['X-API-Key' => 'invalid_key'])
                         ->getJson('/api/v1/device-config?device_uuid=test_uuid');
        $response->assertStatus(401);
    }

    public function test_device_config_works_with_plain_and_hashed_api_keys(): void
    {
        // Setup plain key & hashed key in DB
        $plainKey = 'mob_test_plain_12345';
        $hashedKey = 'sha256:' . hash('sha256', 'mob_test_hashed_67890');

        ApiKey::create(['key_value' => $plainKey, 'label' => 'Plain Key', 'is_active' => true]);
        ApiKey::create(['key_value' => $hashedKey, 'label' => 'Hashed Key', 'is_active' => true]);

        $employee = Employee::create([
            'employee_id' => '000011748',
            'full_name' => 'YUDI SANTOSO',
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'name' => 'HQ Surabaya',
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'radius_meters' => 200,
            'is_active' => true,
        ]);

        $binding = DeviceBinding::create([
            'employee_id' => '000011748',
            'device_uuid' => 'device_yudi_uuid',
            'registration_status' => 'approved',
            'is_active' => true,
        ]);
        $binding->branches()->attach($branch->id);

        // Test with plain key
        $resPlain = $this->withHeaders(['X-API-Key' => $plainKey])
                         ->getJson('/api/v1/device-config?device_uuid=device_yudi_uuid&employee_id=000011748');
        $resPlain->assertStatus(200)
                 ->assertJson(['status' => 'active', 'employee_id' => '000011748', 'employee_name' => 'YUDI SANTOSO']);

        // Test with hashed key client
        $resHashed = $this->withHeaders(['X-API-Key' => 'mob_test_hashed_67890'])
                          ->getJson('/api/v1/device-config?device_uuid=device_yudi_uuid&employee_id=000011748');
        $resHashed->assertStatus(200)
                  ->assertJson(['status' => 'active', 'employee_id' => '000011748']);
    }

    public function test_punch_submission_validates_geofence_and_creates_log(): void
    {
        $plainKey = 'mob_test_key_abc';
        ApiKey::create(['key_value' => $plainKey, 'label' => 'Test Key', 'is_active' => true]);

        $employee = Employee::create([
            'employee_id' => '000011748',
            'full_name' => 'YUDI SANTOSO',
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'name' => 'HQ Surabaya',
            'latitude' => -7.257500,
            'longitude' => 112.752100,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $binding = DeviceBinding::create([
            'employee_id' => '000011748',
            'device_uuid' => 'device_yudi_uuid',
            'registration_status' => 'approved',
            'is_active' => true,
        ]);
        $binding->branches()->attach($branch->id);

        // 1. Clock in inside branch radius (~5 meters away)
        $resInside = $this->withHeaders(['X-API-Key' => $plainKey])
                          ->postJson('/api/v1/punch', [
                              'employee_id' => '000011748',
                              'device_uuid' => 'device_yudi_uuid',
                              'latitude' => -7.257510,
                              'longitude' => 112.752110,
                              'punch_type' => 'In',
                              'client_punch_id' => 'client_uuid_101',
                          ]);

        $resInside->assertStatus(200)
                  ->assertJson(['status' => 'ok', 'matched_branch_id' => $branch->id]);

        $this->assertDatabaseHas('punch_logs', [
            'employee_id' => '000011748',
            'client_punch_id' => 'client_uuid_101',
            'punch_type' => 'In',
        ]);

        // 2. Clock in outside radius (~5000 meters away)
        $resOutside = $this->withHeaders(['X-API-Key' => $plainKey])
                           ->postJson('/api/v1/punch', [
                               'employee_id' => '000011748',
                               'device_uuid' => 'device_yudi_uuid',
                               'latitude' => -7.300000,
                               'longitude' => 112.752100,
                               'punch_type' => 'In',
                               'client_punch_id' => 'client_uuid_102',
                           ]);

        $resOutside->assertStatus(422)
                   ->assertJson(['status' => 'rejected']);
    }
}
