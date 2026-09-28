<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use InteractsWithTenants, RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_guest_is_sent_to_login_from_protected_surfaces(): void
    {
        $this->get('/staff')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/platform')->assertRedirect('/login');
    }

    public function test_active_user_signs_in_and_lands_on_staff_home(): void
    {
        [$a] = $this->twoTenants();
        $user = $this->userIn($a, ['email' => 'ann@example.com']);

        $this->post('/login', ['email' => 'ann@example.com', 'password' => 'password'])
            ->assertRedirect('/staff');

        $this->assertAuthenticatedAs($user);
    }

    public function test_platform_admin_lands_on_platform_console(): void
    {
        $admin = User::factory()->create(['email' => 'root@example.com']);
        $admin->forceFill(['is_platform_admin' => true])->save();

        $this->post('/login', ['email' => 'root@example.com', 'password' => 'password'])
            ->assertRedirect('/platform');
    }

    public function test_five_failed_attempts_lock_the_account_for_fifteen_minutes(): void
    {
        [$a] = $this->twoTenants();
        $this->userIn($a, ['email' => 'ann@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'ann@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        // Even the right password is refused while locked out.
        $this->post('/login', ['email' => 'ann@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(16)->minutes();
        $this->post('/login', ['email' => 'ann@example.com', 'password' => 'password'])->assertRedirect('/staff');
        $this->assertAuthenticated();
    }

    public function test_successful_sign_in_resets_failed_attempt_counter(): void
    {
        [$a] = $this->twoTenants();
        $this->userIn($a, ['email' => 'ann@example.com']);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => 'ann@example.com', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'ann@example.com', 'password' => 'password'])->assertRedirect('/staff');
        $this->post('/logout');

        $this->post('/login', ['email' => 'ann@example.com', 'password' => 'wrong']);
        $this->post('/login', ['email' => 'ann@example.com', 'password' => 'password'])->assertRedirect('/staff');
    }

    public function test_deactivated_user_cannot_sign_in_and_sessions_are_invalidated(): void
    {
        [$a] = $this->twoTenants();
        $user = $this->userIn($a, ['email' => 'ann@example.com']);
        DB::table('sessions')->insert([
            'id' => 'existing-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
        ]);

        $user->deactivate();

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->post('/login', ['email' => 'ann@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_of_suspended_tenant_cannot_sign_in(): void
    {
        [$a] = $this->twoTenants();
        $this->userIn($a, ['email' => 'ann@example.com']);
        $a->suspend();

        $this->post('/login', ['email' => 'ann@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_without_tenant_who_is_not_platform_admin_cannot_sign_in(): void
    {
        User::factory()->create(['email' => 'stray@example.com']);

        $this->post('/login', ['email' => 'stray@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_password_reset_link_is_sent(): void
    {
        Notification::fake();
        [$a] = $this->twoTenants();
        $user = $this->userIn($a, ['email' => 'ann@example.com']);

        $this->post('/forgot-password', ['email' => 'ann@example.com'])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
