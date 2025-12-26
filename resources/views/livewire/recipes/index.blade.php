<?php

use App\Models\User;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $search = '';

    public function rendering(View $view): void
    {
        if (user()) {
            $view->layout('layouts.app');
        } else {
            $view->layout('layouts.guest');
        }
    }
}; ?>

<section class="w-full">
    @php
        $recipes = Recipe::with(['ratings'])->latest()->get();
    @endphp

    <div class="grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
        @foreach($recipes as $recipe)
            <article class="bg-white rounded-lg shadow-sm overflow-hidden">
                {{-- @php
                    $photo = $recipe->photos->first() ?? null;
                    $img = $photo['conversions']['thumbnail'] ?? $photo['url'] ?? asset('images/placeholder.png');
                @endphp

                <img src="{{ $img }}" alt="{{ $recipe->name }}" class="w-full h-48 object-cover"> --}}

                <div class="p-4">
                    <h3 class="text-lg font-semibold">{{ $recipe->name }}</h3>

                    <div class="mt-2 text-sm text-gray-600">
                        <span class="font-medium">Rating:</span>
                        {{ number_format($recipe->stars_average, 1) }} ({{ $recipe->ratings_count }})
                    </div>

                    <div class="mt-1 text-sm text-gray-600">
                        <span class="font-medium">Complexity:</span>
                        {{ $recipe->complexity_text }}
                    </div>

                    <div class="mt-3 text-sm text-gray-700">
                        {!! nl2br(e(Str::limit(strip_tags($recipe->instructions), 300))) !!}
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</section>
