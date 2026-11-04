<?php

namespace App\Livewire\Client\Kb;

use App\Domain\Content\Models\KbArticle;
use App\Domain\Content\Models\KbCategory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class KbList extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    public function render(): View
    {
        // Búsqueda de artículos publicados.
        $articles = KbArticle::query()
            ->visible()
            ->with('category')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('title', 'like', "%{$this->search}%")
                        ->orWhere('body', 'like', "%{$this->search}%")
                        ->orWhere('excerpt', 'like', "%{$this->search}%");
                });
            })
            ->orderByDesc('published_at')
            ->get();

        // Agrupa por categoría para mostrar.
        $grouped = $articles->groupBy(fn ($a) => $a->category?->name ?? 'Sin categoría');

        return view('livewire.client.kb.kb-list', [
            'grouped' => $grouped,
            'total' => $articles->count(),
        ])->layout('layouts.app', ['area' => 'client']);
    }
}
