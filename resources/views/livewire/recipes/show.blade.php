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
    public float $servings = 1;

    public function mount(): void
    {
        $this->servings = $this->recipe->servings;
    }

    public function rendering(View $view): void
    {
        $this->recipe->load([
            'ingredients',
            'ingredients.ingredients',
            'ingredients.ingredientGroup',
            'ingredients.food',
            'ingredients.unit',
            'ingredientGroups',
        ]);

        if (user()) {
            $view->layout('layouts.app');
        } else {
            $view->layout('layouts.guest');
        }
    }

    public function increaseServings(): void
    {
        $this->servings += 1;
    }

    public function decreaseServings(): void
    {
        if ($this->servings <= 1) {
            return;
        }

        $this->servings -= 1;
    }
}; ?>

<section class="w-full max-w-6xl mx-auto space-y-8">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('recipes.index') }}" icon="home" />
        <flux:breadcrumbs.item href="#">{{ $recipe->category->name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $recipe->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    @if ($recipe->photos->isNotEmpty())
        <div class="overflow-hidden aspect-square sm:aspect-16/9 md:aspect-21/9 rounded-xl">
            <img
                src="{{ $recipe->photos->first()->url() }}?v={{ $recipe->updated_at->timestamp }}"
                alt="{{ $recipe->name }}"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
            >
        </div>
    @endif

    <div class="space-y-2">
        <flux:heading level="1" size="xl">{{ $recipe->name }}</flux:heading>

        @if ($recipe->ratings_count)
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-0.5">
                    @for ($i = 0; $i < $recipe->ratings_stars; $i++)
                        <flux:icon.star class="size-5" variant="solid" />
                    @endfor

                    @for ($i = $recipe->ratings_stars; $i < 5; $i++)
                        <flux:icon.star class="size-5 text-amber-400" />
                    @endfor
                </div>

                <flux:text class="leading-tight">
                    ({{ __(':stars stars / :ratings ratings', [
                        'stars' => $recipe->ratings_stars,
                        'ratings' => $recipe->ratings_count
                    ]) }})
                </flux:text>
            </div>
        @endif
    </div>

    <div class="flex flex-col md:flex-row gap-4 md:gap-16">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <flux:button variant="filled" size="xs" icon="minus" :disabled="$servings <= 1" wire:click="decreaseServings()" />
                <flux:button variant="filled" size="xs" icon="plus" wire:click="increaseServings()" />
            </div>

            <flux:text variant="strong" class="text-lg">
                {{ $servings }} {{ $recipe->serving_type ?? __('servings') }}
            </flux:text>

            <flux:tooltip toggleable>
                <flux:button
                    icon="information-circle"
                    variant="ghost"
                    size="sm"
                    class="{{ $servings === $recipe->servings ? 'invisible pointer-events-none' : '' }}"
                />

                <flux:tooltip.content class="max-w-[20rem] space-y-2">
                    <p>{{ __('Please note: The conversion is done automatically. Not all recipes can be converted automatically on a 1:1 basis.') }}</p>
                    <p>{{ __('Information in the text and cooking and baking times have not been adjusted automatically.') }}</p>
                </flux:tooltip.content>
            </flux:tooltip>
        </div>

        <div class="flex items-center gap-6">
            @if ($recipe->preparation_time)
                <flux:badge variant="pill" icon="clock">
                    {{ $recipe->preparation_time->format('H:i') }}
                </flux:badge>
            @endif

            <flux:badge variant="pill" icon="{{ $recipe->complexity->icon() }}" color="{{ $recipe->complexity->color() }}">
                {{ $recipe->complexity->label() }}
            </flux:badge>

            @if ($recipe->cookbook)
                <flux:badge variant="pill" icon="bookmark">
                    {{ $recipe->cookbook->name }}
                </flux:badge>
            @endif

            <flux:badge variant="pill" icon="user-circle">
                {{ $recipe->author->name }}
            </flux:badge>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-8 gap-16 mt-20">
        <flux:card class="space-y-6 sm:col-span-3 bg-transparent! dark:bg-transparent! border-none px-0">
            <div>
                <flux:heading size="lg" level="2" class="mb-2">
                    {{ __('Ingredients') }}
                </flux:heading>
            </div>

            <div class="space-y-6">
                @foreach ($recipe->ingredients->groupBy('ingredient_group_id') as $key => $ingredients)
                    <flux:separator />

                    <div class="space-y-2">
                        <flux:heading size="lg" level="3" class="mb-4">
                            {{ $recipe->ingredientGroups->first(fn ($g) => $g->id == $key)?->name }}
                        </flux:heading>

                        @foreach ($ingredients->filter(fn ($i) => ! $i->ingredient_id) as $ingredient)
                            <div>
                                <div class="flex flex-cols-2 gap-2 text-lg">
                                    <div class="w-full max-w-32 sm:w-20">
                                        <flux:text class="text-lg" variant="strong">
                                            {{ $ingredient->getAmountAndUnit(1 / $recipe->servings * $servings) }}
                                        </flux:text>
                                    </div>
                                    <flux:text class="text-lg">{{ $ingredient->food?->name }}</flux:text>
                                </div>

                                @foreach ($ingredient->ingredients as $i)
                                    <div class="flex flex-cols-2 gap-2 ml-2">
                                        <flux:text>{{ __('→ or :ingredient', ['ingredient' => $i->name]) }}</flux:text>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </flux:card>

        <flux:card class="space-y-6 sm:col-span-5">
            <flux:heading size="lg" level="2">
                {{ __('Preparation') }}
            </flux:heading>

            <div class="prose dark:prose-invert prose-li:my-0">
                {!! $recipe->instructions !!}
            </div>
        </flux:card>
    </div>

    {{-- <flux:editor :value="$recipe->instructions" /> --}}
</section>
