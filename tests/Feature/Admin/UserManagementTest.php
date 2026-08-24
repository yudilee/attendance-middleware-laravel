<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_user_management(): void
    {
        $response = $this->get('/admin/users');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_admin_can_view_user_roster(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_admin_can_create_new_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'HR Manager',
            'email' => 'hr@hartonomotor.com',
            'password' => 'securepassword123',
            'role' => 'hr',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'hr@hartonomotor.com',
            'role' => 'hr',
        ]);
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $targetUser = User::factory()->create(['role' => 'viewer']);

        $response = $this->actingAs($admin)->put("/admin/users/{$targetUser->id}", [
            'name' => 'Updated Name',
            'email' => $targetUser->email,
            'role' => 'manager',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'Updated Name',
            'role' => 'manager',
        ]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->delete("/admin/users/{$admin->id}");

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $targetUser = User::factory()->create();

        $response = $this->actingAs($admin)->delete("/admin/users/{$targetUser->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }
}
