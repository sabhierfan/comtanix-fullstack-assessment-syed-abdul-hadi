<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_log_in_and_receive_a_token(): void
    {
        $user = User::factory()->create(['email' => 'staff@comtanix.test']);

        $this->postJson('/api/login', ['email' => 'staff@comtanix.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'staff');
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'staff@comtanix.test']);

        $this->postJson('/api/login', ['email' => 'staff@comtanix.test', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_protected_routes_require_a_token_and_return_json_401(): void
    {
        // No Accept header on purpose: the API must still answer with JSON, not a redirect.
        $this->get('/api/quote-requests')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        User::factory()->create(['email' => 'staff@comtanix.test']);
        $token = $this->postJson('/api/login', ['email' => 'staff@comtanix.test', 'password' => 'password'])->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'staff@comtanix.test']);

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/login', ['email' => 'staff@comtanix.test', 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'staff@comtanix.test', 'password' => 'wrong'])->assertTooManyRequests();
    }
}
