<?php

namespace Tests\Feature\Content;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\KbArticle;
use App\Domain\Content\Models\KbCategory;
use App\Livewire\Admin\Kb\KbArticleForm;
use App\Livewire\Admin\Kb\KbCategoryForm;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KbManagementTest extends TestCase
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

    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Client->value);
        \App\Domain\Clients\Models\Client::factory()->create(['user_id' => $user->id]);

        return $user->refresh();
    }

    public function test_admin_can_create_category_and_article(): void
    {
        Livewire::actingAs($this->admin())
            ->test(KbCategoryForm::class)
            ->set('name', 'Guías rápidas')
            ->call('save')
            ->assertRedirect(route('admin.kb.categories.index'));

        $category = KbCategory::where('name', 'Guías rápidas')->firstOrFail();
        $this->assertDatabaseHas('audits', ['action' => AuditAction::KbCategoryCreated->value]);

        Livewire::actingAs($this->admin())
            ->test(KbArticleForm::class)
            ->set('kb_category_id', (string) $category->id)
            ->set('title', 'Cómo empezar')
            ->set('body', 'Contenido del artículo.')
            ->set('status', ContentStatus::Published->value)
            ->call('save')
            ->assertRedirect(route('admin.kb.articles.index'));

        $this->assertDatabaseHas('kb_articles', ['title' => 'Cómo empezar', 'kb_category_id' => $category->id]);
        $this->assertDatabaseHas('audits', ['action' => AuditAction::KbArticleCreated->value]);
    }

    public function test_deleting_category_cascades_articles(): void
    {
        $category = KbCategory::factory()->create();
        KbArticle::factory()->create(['kb_category_id' => $category->id]);

        app(\App\Domain\Content\Services\KbService::class)->deleteCategory($category);

        $this->assertDatabaseMissing('kb_categories', ['id' => $category->id]);
        $this->assertSame(0, KbArticle::where('kb_category_id', $category->id)->count());
    }

    public function test_client_kb_search_finds_published_articles(): void
    {
        $category = KbCategory::factory()->create();
        KbArticle::factory()->create([
            'kb_category_id' => $category->id,
            'title' => 'Restablecer contraseña',
        ]);
        KbArticle::factory()->draft()->create([
            'kb_category_id' => $category->id,
            'title' => 'Articulo Borrador Oculto',
        ]);

        // Sin búsqueda: ve el publicado, no el borrador.
        $this->actingAs($this->clientUser())
            ->get('/client/kb')
            ->assertOk()
            ->assertSee('Restablecer contraseña')
            ->assertDontSee('Articulo Borrador Oculto');

        // Con búsqueda que no coincide.
        $this->actingAs($this->clientUser())
            ->get('/client/kb?q=inexistente')
            ->assertOk()
            ->assertDontSee('Restablecer contraseña');
    }
}
