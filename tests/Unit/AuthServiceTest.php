<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthService();
    }

    // ── register ──────────────────────────────────────────────────────────────

    public function test_register_creates_user_in_database(): void
    {
        $this->service->register([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'secret123',
        ]);

        $this->assertDatabaseHas('users', [
            'name'  => 'Alice',
            'email' => 'alice@example.com',
        ]);
    }

    public function test_register_returns_user_model(): void
    {
        $user = $this->service->register([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'secret123',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Alice', $user->name);
        $this->assertEquals('alice@example.com', $user->email);
    }

    public function test_register_hashes_password(): void
    {
        $user = $this->service->register([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'password' => 'secret123',
        ]);

        $this->assertNotEquals('secret123', $user->password);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    // ── login ─────────────────────────────────────────────────────────────────

    public function test_login_returns_user_with_correct_credentials(): void
    {
        User::factory()->create([
            'email'    => 'alice@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $user = $this->service->login([
            'email'    => 'alice@example.com',
            'password' => 'secret123',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('alice@example.com', $user->email);
    }

    public function test_login_throws_for_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'alice@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->expectException(ValidationException::class);

        $this->service->login([
            'email'    => 'alice@example.com',
            'password' => 'wrong-password',
        ]);
    }

    public function test_login_throws_for_unknown_email(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->login([
            'email'    => 'ghost@example.com',
            'password' => 'any-password',
        ]);
    }

    // ── updateProfile ─────────────────────────────────────────────────────────

    public function test_update_profile_persists_name_and_email(): void
    {
        $user = User::factory()->create([
            'name'  => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $updated = $this->service->updateProfile($user, [
            'name'  => 'New Name',
            'email' => 'new@example.com',
        ]);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('new@example.com', $updated->email);
        $this->assertDatabaseHas('users', ['name' => 'New Name', 'email' => 'new@example.com']);
    }

    public function test_update_profile_returns_fresh_user(): void
    {
        $user = User::factory()->create();

        $result = $this->service->updateProfile($user, [
            'name'  => 'Fresh',
            'email' => 'fresh@example.com',
        ]);

        $this->assertInstanceOf(User::class, $result);
    }

    // ── updatePassword ────────────────────────────────────────────────────────

    public function test_update_password_hashes_and_persists(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->service->updatePassword($user, 'new-password');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_update_password_invalidates_old_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->service->updatePassword($user, 'new-password');

        $this->assertFalse(Hash::check('old-password', $user->fresh()->password));
    }
}
