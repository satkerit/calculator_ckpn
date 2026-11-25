<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Registrasi publik sengaja ditutup (routes/auth.php): akun hanya boleh dibuat
 * oleh user ADMIN yang aktif dan memiliki permission `user.create`.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/register')->assertRedirect(route('login'));
    }

    public function test_users_without_permission_receive_forbidden(): void
    {
        $this->actingAs($this->makeUserWithRole('VIEWER', []))
            ->get('/register')
            ->assertForbidden();
    }

    public function test_admin_with_permission_can_create_user(): void
    {
        $admin = $this->makeUserWithRole('ADMIN', ['user.create']);

        DB::table('roles')->insert([
            'kode_role' => 'VIEWER',
            'nama_role' => 'Viewer',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('user.index'));
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function makeUserWithRole(string $roleCode, array $permissions): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'kode_role' => $roleCode,
            'nama_role' => ucfirst(strtolower($roleCode)),
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($permissions as $permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                'kode_permission' => $permission,
                'nama_permission' => $permission,
                'name' => $permission,
                'display_name' => $permission,
                'module' => 'USER_MANAGEMENT',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('role_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return User::factory()->create(['role_id' => $roleId]);
    }
}
