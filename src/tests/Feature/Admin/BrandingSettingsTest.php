<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Notifications\Models\Setting;
use App\Livewire\Admin\System\BrandingSettings;
use App\Support\Branding;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_default_name_when_not_configured(): void
    {
        $this->assertSame('Portal WORTEV', app(Branding::class)->name());
        $this->assertNull(app(Branding::class)->logoUrl());
        $this->assertNull(app(Branding::class)->faviconUrl());
    }

    public function test_admin_can_change_portal_name(): void
    {
        Livewire::actingAs($this->admin())
            ->test(BrandingSettings::class)
            ->set('name', 'Mi Portal')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Mi Portal', Setting::get(Branding::KEY_NAME));
        $this->assertSame('Mi Portal', app(Branding::class)->name());
    }

    public function test_admin_can_upload_logo_and_favicon(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(BrandingSettings::class)
            ->set('name', 'Marca')
            ->set('logo', UploadedFile::fake()->image('logo.png', 200, 60))
            ->set('favicon', UploadedFile::fake()->image('favicon.png', 32, 32))
            ->call('save')
            ->assertHasNoErrors();

        $logoPath = Setting::get(Branding::KEY_LOGO_PATH);
        $faviconPath = Setting::get(Branding::KEY_FAVICON_PATH);

        $this->assertNotNull($logoPath);
        $this->assertNotNull($faviconPath);
        Storage::disk('public')->assertExists($logoPath);
        Storage::disk('public')->assertExists($faviconPath);
        $this->assertNotNull(app(Branding::class)->logoUrl());
    }

    public function test_removing_logo_clears_setting_and_file(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(BrandingSettings::class)
            ->set('name', 'Marca')
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->call('save');

        $logoPath = Setting::get(Branding::KEY_LOGO_PATH);
        Storage::disk('public')->assertExists($logoPath);

        Livewire::actingAs($this->admin())
            ->test(BrandingSettings::class)
            ->call('removeLogo');

        $this->assertNull(Setting::get(Branding::KEY_LOGO_PATH));
        Storage::disk('public')->assertMissing($logoPath);
    }

    public function test_name_is_required(): void
    {
        Livewire::actingAs($this->admin())
            ->test(BrandingSettings::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_logo_must_be_an_image(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(BrandingSettings::class)
            ->set('name', 'Marca')
            ->set('logo', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasErrors('logo');
    }

    public function test_support_role_cannot_access_branding(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value);

        $this->actingAs($support)
            ->get(route('admin.settings.branding'))
            ->assertForbidden();
    }

    public function test_configured_name_appears_in_admin_layout(): void
    {
        Setting::put(Branding::KEY_NAME, 'WORTEV Hosting');

        $this->actingAs($this->admin())
            ->get(route('admin.settings.branding'))
            ->assertOk()
            ->assertSee('WORTEV Hosting');
    }
}
