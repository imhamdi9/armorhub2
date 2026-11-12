<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_reset_switch_restores_a_locked_out_admin_and_nothing_else_changes_without_it(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->artisan('platform:create-admin', ['email' => 'boss@example.com', '--password' => 'first-password-1'])->assertSuccessful();
        $boss = User::where('email', 'boss@example.com')->first();
        $boss->forceFill(['is_active' => false])->save();
        $boss->createToken('old');

        // Without the switch, an existing account is left alone: a changed environment variable cannot take it over.
        $this->artisan('platform:create-admin', ['email' => 'boss@example.com', '--password' => 'attacker-pass-9', '--if-missing' => true])->assertSuccessful();
        $this->assertTrue(\Hash::check('first-password-1', $boss->fresh()->password));

        // With it, the password is replaced, the account is active again, and old sessions are gone.
        $this->artisan('platform:create-admin', ['email' => 'boss@example.com', '--password' => 'new-password-77', '--reset-password' => true])->assertSuccessful();
        $boss->refresh();
        $this->assertTrue(\Hash::check('new-password-77', $boss->password));
        $this->assertTrue((bool) $boss->is_active);
        $this->assertTrue($boss->hasRole('platform-admin'));
        $this->assertSame(0, $boss->tokens()->count());
        $this->postJson('/api/login', ['email' => 'boss@example.com', 'password' => 'new-password-77'])->assertOk();

        // A weak password is refused.
        $this->artisan('platform:create-admin', ['email' => 'boss@example.com', '--password' => 'short', '--reset-password' => true])->assertFailed();
    }
}
