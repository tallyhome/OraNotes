<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_admin_can_create_disable_and_soft_delete_a_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Nora',
                'email' => 'nora@example.test',
                'password' => 'Secret-ora-12',
                'password_confirmation' => 'Secret-ora-12',
                'role' => 'user',
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'nora@example.test')->first();
        $this->assertNotNull($user);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), ['is_active' => false])
            ->assertRedirect();

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret-ora-12'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect();

        $this->assertSoftDeleted($user);
    }

    #[Test]
    public function test_non_admin_cannot_create_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.users.store'), [
                'name' => 'X',
                'email' => 'x@example.test',
                'password' => 'Secret-ora-12',
                'password_confirmation' => 'Secret-ora-12',
                'role' => 'user',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function test_admin_sections_render(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.health'))->assertOk();
        $this->actingAs($admin)->get(route('admin.system'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk();
        $this->actingAs($admin)->get(route('admin.security'))->assertOk();
        $this->actingAs($admin)->get(route('admin.storage'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.show', $admin))->assertOk();
    }
}
