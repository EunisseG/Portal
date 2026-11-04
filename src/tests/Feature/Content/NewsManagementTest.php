<?php

namespace Tests\Feature\Content;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\News;
use App\Domain\Content\Services\NewsService;
use App\Livewire\Admin\News\NewsForm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class NewsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake(NewsService::DISK);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_admin_can_create_news_with_cover(): void
    {
        Livewire::actingAs($this->admin())
            ->test(NewsForm::class)
            ->set('title', 'Anuncio importante')
            ->set('body', '## Hola\n\nContenido de prueba.')
            ->set('status', ContentStatus::Published->value)
            ->set('cover', UploadedFile::fake()->image('portada.jpg'))
            ->call('save')
            ->assertRedirect(route('admin.news.index'));

        $news = News::where('title', 'Anuncio importante')->firstOrFail();
        $this->assertNotEmpty($news->slug);
        $this->assertNotNull($news->cover_path);
        $this->assertNotNull($news->published_at);
        Storage::disk(NewsService::DISK)->assertExists($news->cover_path);
        $this->assertDatabaseHas('audits', ['action' => AuditAction::NewsCreated->value]);
    }

    public function test_billing_role_cannot_create_news(): void
    {
        // billing no tiene news.manage.
        $billing = User::factory()->create();
        $billing->assignRole(RoleName::Billing->value);

        Livewire::actingAs($billing)
            ->test(NewsForm::class)
            ->assertForbidden();
    }

    public function test_client_sees_only_published_news(): void
    {
        $client = User::factory()->create();
        $client->assignRole(RoleName::Client->value);
        \App\Domain\Clients\Models\Client::factory()->create(['user_id' => $client->id]);

        News::factory()->create(['title' => 'Noticia Publicada Visible']);
        News::factory()->draft()->create(['title' => 'Noticia Borrador Oculta']);
        News::factory()->create([
            'title' => 'Noticia Futura Oculta',
            'published_at' => now()->addWeek(),
        ]);

        $this->actingAs($client->refresh())
            ->get('/client/noticias')
            ->assertOk()
            ->assertSee('Noticia Publicada Visible')
            ->assertDontSee('Noticia Borrador Oculta')
            ->assertDontSee('Noticia Futura Oculta');
    }
}
