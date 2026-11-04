<?php

namespace App\Livewire\Admin\News;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\News;
use App\Domain\Content\Services\NewsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Livewire\Component;
use Livewire\WithFileUploads;

class NewsForm extends Component
{
    use WithFileUploads;

    public ?News $news = null;

    public string $title = '';
    public string $excerpt = '';
    public string $body = '';
    public string $status = 'draft';
    public string $published_at = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $cover = null;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $gallery = [];

    public function mount(?News $news = null): void
    {
        if ($news && $news->exists) {
            Gate::authorize('update', News::class);
            $this->news = $news->load('images');
            $this->title = $news->title;
            $this->excerpt = (string) $news->excerpt;
            $this->body = $news->body;
            $this->status = $news->status->value;
            $this->published_at = $news->published_at?->format('Y-m-d\TH:i') ?? '';
        } else {
            Gate::authorize('create', News::class);
        }
    }

    public function isEditing(): bool
    {
        return $this->news !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
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

    public function save(NewsService $news): mixed
    {
        $data = $this->validate();

        $payload = [
            'title' => $data['title'],
            'excerpt' => $data['excerpt'] ?: null,
            'body' => $data['body'],
            'status' => $data['status'],
            'published_at' => $data['published_at'] ?: null,
        ];

        if ($this->isEditing()) {
            $news->update($this->news, $payload, $this->cover, $this->gallery);
            session()->flash('status', 'Noticia actualizada.');
        } else {
            $news->create($payload, auth()->id(), $this->cover, $this->gallery);
            session()->flash('status', 'Noticia creada.');
        }

        return $this->redirect(route('admin.news.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.news.news-form', [
            'statusOptions' => ContentStatus::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
