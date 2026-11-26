<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Security\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Long-test-password-2026!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_totp_matches_the_rfc_6238_reference_vector_and_rejects_replays(): void
    {
        $totp = new TwoFactor;
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // base32 of "12345678901234567890"

        $this->assertSame(1, $totp->verify($secret, '287082', null, 59));
        $this->assertNull($totp->verify($secret, '287082', 1, 59), 'a used step must not be accepted again');
        $this->assertNull($totp->verify($secret, '000000', null, 59));
        $this->assertNull($totp->verify($secret, '28708', null, 59));
        $this->assertNull($totp->verify($secret, '287082', null, 59 + 120), 'codes expire outside the drift window');
    }

    public function test_full_setup_then_sign_in_requires_the_code(): void
    {
        $totp = new TwoFactor;
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/security/two-factor', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($admin)->post('/admin/security/two-factor', ['password' => self::PASSWORD])->assertRedirect();
        $secret = $admin->fresh()->two_factor_secret;
        $this->assertNotNull($secret);
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled(), 'not enforced until confirmed');

        $this->actingAs($admin)->get('/admin/security')->assertOk()->assertSee('<svg', false)->assertSee('Waiting for confirmation');

        $this->actingAs($admin)->post('/admin/security/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post('/admin/security/two-factor/confirm', ['code' => $this->currentCode($secret)])
            ->assertSessionHas('recoveryCodes');
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'security.two_factor_enabled']);

        auth()->logout();
        $this->post('/admin/login', ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertRedirect('/admin/login/two-factor');
        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->post('/admin/login/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'authentication.two_factor_failed']);

        // Confirmation consumed the current step, so log in with the next one.
        $this->travel(30)->seconds();
        $this->post('/admin/login/two-factor', ['code' => $this->currentCode($secret)])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($totp->verify($secret, $this->currentCode($secret)));
    }

    public function test_recovery_codes_work_once(): void
    {
        $admin = $this->admin();
        $codes = $this->enable($admin);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => self::PASSWORD]);
        $this->post('/admin/login/two-factor', ['recovery_code' => strtoupper($codes[0])])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);

        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => self::PASSWORD]);
        $this->post('/admin/login/two-factor', ['recovery_code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_challenge_cannot_be_reached_without_a_correct_password_and_expires(): void
    {
        $admin = $this->admin();
        $this->enable($admin);

        $this->get('/admin/login/two-factor')->assertRedirect('/admin/login');
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->get('/admin/login/two-factor')->assertRedirect('/admin/login');

        $this->post('/admin/login', ['email' => $admin->email, 'password' => self::PASSWORD]);
        $this->get('/admin/login/two-factor')->assertOk()->assertSee('One more step');
        $this->travel(6)->minutes();
        $this->post('/admin/login/two-factor', ['code' => $this->currentCode($admin->fresh()->two_factor_secret)])
            ->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_turning_off_needs_password_and_a_current_code(): void
    {
        $admin = $this->admin();
        $this->enable($admin);
        $this->travel(30)->seconds();

        $this->actingAs($admin)->delete('/admin/security/two-factor', ['password' => self::PASSWORD, 'code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());

        $this->actingAs($admin)->delete('/admin/security/two-factor', [
            'password' => self::PASSWORD,
            'code' => $this->currentCode($admin->fresh()->two_factor_secret),
        ])->assertRedirect('/admin/security');
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());
        $this->assertNull($admin->fresh()->two_factor_secret);
    }

    public function test_secret_is_encrypted_at_rest_and_never_serialised(): void
    {
        $admin = $this->admin();
        $this->enable($admin);
        $raw = \DB::table('users')->where('id', $admin->id)->value('two_factor_secret');

        $this->assertNotSame($admin->fresh()->two_factor_secret, $raw);
        $this->assertArrayNotHasKey('two_factor_secret', $admin->fresh()->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $admin->fresh()->toArray());
    }

    public function test_cli_reset_turns_two_factor_off(): void
    {
        $admin = $this->admin();
        $this->enable($admin);

        $this->artisan('portfolio:two-factor:reset', ['email' => $admin->email])
            ->expectsConfirmation("Turn off two-factor sign-in for {$admin->email}?", 'yes')
            ->assertSuccessful();
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'password' => self::PASSWORD]);
    }

    /** @return list<string> */
    private function enable(User $admin): array
    {
        $this->actingAs($admin)->post('/admin/security/two-factor', ['password' => self::PASSWORD]);
        $response = $this->actingAs($admin)->post('/admin/security/two-factor/confirm', [
            'code' => $this->currentCode($admin->fresh()->two_factor_secret),
        ]);
        $codes = $response->getSession()->get('recoveryCodes');
        auth()->logout();
        $this->flushSession();

        return $codes;
    }

    private function currentCode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $key .= chr(bindec($byte));
            }
        }

        return (new TwoFactor)->codeAt($key, intdiv(now()->timestamp, 30));
    }
}
