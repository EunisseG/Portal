<?php

namespace App\Livewire\Client\Kb;

use App\Domain\Content\Models\KbArticle;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class KbShow extends Component
{
    public KbArticle $article;

    public function mount(string $slug): void
    {
        $this->article = KbArticle::visible()->with('category')->where('slug', $slug)->firstOrFail();
    }

    public function render(): View
    {
        return view('livewire.client.kb.kb-show')
            ->layout('layouts.app', ['area' => 'client']);
    }
}
