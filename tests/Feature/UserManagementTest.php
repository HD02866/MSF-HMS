<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_admin_can_create_user_and_see_success_message(): void
    {
        $admin = User::where('username', 'admin')->first();
        $role = Role::where('name', 'Card Officer')->first();
        $department = Department::where('name', 'Card Room')->first();

        $response = $this->actingAs($admin)->post('/users', [
            'full_name' => 'New Card Officer',
            'username' => 'card.officer2',
            'password' => 'password123',
            'role_id' => $role->id,
            'department_id' => $department->id,
            'phone' => '0911000000',
        ]);

        $response
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'username' => 'card.officer2',
            'full_name' => 'New Card Officer',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_user_without_department(): void
    {
        $admin = User::where('username', 'admin')->first();
        $role = Role::where('name', 'General Manager')->first();

        $response = $this->actingAs($admin)->post('/users', [
            'full_name' => 'General Manager Two',
            'username' => 'gm.two',
            'password' => 'password123',
            'role_id' => $role->id,
            'department_id' => '',
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'username' => 'gm.two',
            'department_id' => null,
        ]);
    }

    public function test_duplicate_username_shows_validation_error(): void
    {
        $admin = User::where('username', 'admin')->first();
        $role = Role::where('name', 'Card Officer')->first();

        $response = $this->actingAs($admin)->post('/users', [
            'full_name' => 'Duplicate Admin',
            'username' => 'admin',
            'password' => 'password123',
            'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_admin_can_update_and_deactivate_user(): void
    {
        $admin = User::where('username', 'admin')->first();
        $role = Role::where('name', 'Card Officer')->first();

        $user = User::create([
            'full_name' => 'Temp User',
            'username' => 'temp.user',
            'password' => 'password123',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put("/users/{$user->id}", [
                'full_name' => 'Updated User',
                'username' => 'temp.user',
                'role_id' => $role->id,
                'department_id' => '',
                'phone' => '',
                'is_active' => true,
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'full_name' => 'Updated User',
        ]);

        $this->actingAs($admin)
            ->delete("/users/{$user->id}")
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);
    }
}
