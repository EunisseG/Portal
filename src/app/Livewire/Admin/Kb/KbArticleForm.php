<?php

namespace App\Livewire\Admin\Kb;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\KbArticle;
use App\Domain\Content\Models\KbCategory;
use App\Domain\Content\Services\KbService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;
use Livewire\WithFileUploads;

class KbArticleForm extends Component
{
    use WithFileUploads;

    public ?KbArticle $article = null;

    public string $kb_category_id = '';
    public string $title = '';
    public string $excerpt = '';
    public string $body = '';
    public string $status = 'draft';
    public string $published_at = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $cover = null;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $gallery = [];

    public function mount(?KbArticle $article = null): void
    {
        if ($article && $article->exists) {
            Gate::authorize('update', KbArticle::class);
            $this->article = $article->load('images');
            $this->kb_category_id = (string) $article->kb_category_id;
            $this->title = $article->title;
            $this->excerpt = (string) $article->excerpt;
            $this->body = $article->body;
            $this->status = $article->status->value;
            $this->published_at = $article->published_at?->format('Y-m-d\TH:i') ?? '';
        } else {
            Gate::authorize('create', KbArticle::class);
        }
    }

    public function isEditing(): bool
    {
        return $this->article !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'kb_category_id' => ['required', Rule::exists('kb_categories', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'status' => ['required', new Enum(ContentStatus::class)],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'max:4096'],
            'gallery' => ['array', 'max:10'],
            'gallery.*' => ['image', 'max:4096'],
        ];
    }

    public function save(KbService $kb): mixed
    {
        $data = $this->validate();

        $payload = [
            'kb_category_id' => $data['kb_category_id'],
            'title' => $data['title'],
            'excerpt' => $data['excerpt'] ?: null,
            'body' => $data['body'],
            'status' => $data['status'],
            'published_at' => $data['published_at'] ?: null,
        ];

        if ($this->isEditing()) {
            $kb->updateArticle($this->article, $payload, $this->cover, $this->gallery);
            session()->flash('status', 'Artículo actualizado.');
        } else {
            $kb->createArticle($payload, auth()->id(), $this->cover, $this->gallery);
            session()->flash('status', 'Artículo creado.');
        }

        return $this->redirect(route('admin.kb.articles.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.kb.kb-article-form', [
            'categories' => KbCategory::orderBy('name')->get(),
            'statusOptions' => ContentStatus::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
