<?php

use App\Livewire\Traits\Sortable;
use App\Models\User;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    public Recipe $recipe;

    public function rendering(View $view): void
    {
        if (user()) {
            $view->layout('layouts.app');
        } else {
            $view->layout('layouts.guest');
        }
    }
}; ?>

<section class="max-w-6xl mx-auto space-y-12 py-4">
    {{ $recipe->name }}
</section>
