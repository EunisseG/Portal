<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_two_factor_feature_is_enabled(): void
    {
        $this->assertTrue(Features::enabled(Features::twoFactorAuthentication()));
    }

    public function test_user_can_enable_confirm_and_disable_two_factor(): void
    {
        $user = User::factory()->create();

        // La confirmación de contraseña está activa: la simulamos como ya hecha.
        $this->actingAs($user);
        $this->session(['auth.password_confirmed_at' => time()]);

        // 1) Habilitar: genera el secreto (aún sin confirmar).
        $this->post('/user/two-factor-authentication')->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        // 2) Confirmar con un código TOTP válido derivado del secreto.
        $secret = decrypt($user->two_factor_secret);
        $code = (new Google2FA())->getCurrentOtp($secret);

        $this->post('/user/confirmed-two-factor-authentication', ['code' => $code])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertTrue($user->hasEnabledTwoFactorAuthentication());
        $this->assertNotEmpty($user->recoveryCodes());

        // 3) Desactivar.
        $this->delete('/user/two-factor-authentication')->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertNull($user->two_factor_secret);
    }

    public function test_admin_profile_page_shows_two_factor_section(): void
    {
        // En el área admin, el 2FA vive en /profile. Los clientes lo tienen en
        // "Mi Cuenta › Ajustes de Seguridad" (cubierto en ClientAccountTest).
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Autenticación en dos pasos');
    }

    public function test_client_security_page_shows_two_factor_section(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Client\Account\SecuritySettings::class)
            ->assertOk()
            ->assertSee('Autenticación en dos pasos');
    }
}
