<?php

use App\Enums\Complexity;
use App\Livewire\Traits\Sortable;
use App\Models\Category;
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
    use Sortable;
    use WithPagination;

    /** @var Collection<int, Recipe> */
    public Collection $recipes;

    public int $page = 1;

    public int $pagination = 12;

    #[Url(as: 'search', history: true, keep: false)]
    public string $search = '';

    #[Url(as: 'quick', history: true, keep: false)]
    public bool $quick = false;

    #[Url(as: 'complexity', history: true, keep: false)]
    public ?string $complexity = null;

    #[Url(as: 'category', history: true, keep: false)]
    public ?int $category = null;

    /**
     * @var array<string|array<string>>
     */
    protected array $searchable = [
        'name',
        'instructions',
    ];

    /**
     * @var array<string>
     */
    protected array $sortable = [
        'name',
        'complexity',
        'created_at',
    ];

    public function mount(): void
    {
        $this->defaultSortField('created_at');
        $this->defaultSortDirection('desc');

        $this->recipes = collect();
    }

    public function rendering(View $view): void
    {
        if (user()) {
            $view->layout('layouts.app');
        } else {
            $view->layout('layouts.guest');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $recipes = Recipe::search($this->searchable, $this->search)
            ->when(auth()->check(), fn ($q) => $q->where(fn ($subQ) => $subQ->whereNull('cookbook_id')->orWhere('user_id', user()->id)))
            ->when(! auth()->check(), fn ($q) => $q->whereNull('cookbook_id'))
            ->when(in_array($this->sortBy, $this->sortable), fn ($q) => $q->orderBy($this->sortBy, $this->sortDirection)->orderBy('id', 'desc'))
            ->when($this->quick, fn ($q) => $q->where('preparation_time', '<=', '00:30:00'))
            ->when($this->complexity, fn ($q) => $q->where('complexity', $this->complexity))
            ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
            ->latest()
            ->paginate($this->pagination, page: $this->page);

        $this->recipes = $this->recipes->concat($recipes->items());

        return [
            'hasMorePages' => $recipes->hasMorePages(),
            'total' => $recipes->total(),
            'categories' => Category::get(),
        ];
    }

    public function updated(string $property, mixed $value): void
    {
        if ($property === 'search') {
            $this->resetPage();
        }
    }

    public function resetPage(): void
    {
        $this->page = 1;
        $this->recipes = collect();
    }

    public function loadMore(): void
    {
        $this->page++;
    }

    public function setQuick(): void
    {
        $this->quick = ! $this->quick;
        $this->resetPage();
    }

    public function setComplexity(string $complexity): void
    {
        $this->complexity = $this->complexity === $complexity ? null : $complexity;
        $this->resetPage();
    }

    public function setCategory(?int $category = null): void
    {
        $this->category = $category;
        $this->resetPage();
    }
}; ?>

<section class="w-full max-w-6xl mx-auto space-y-12 py-4">
    <div class="flex flex-wrap items-center gap-4">
        <flux:button
            variant="{{ $quick ? 'primary' : 'filled' }}"
            icon="rocket-launch"
            size="sm"
            wire:click="setQuick()"
        >
            {{ __('Max. 30 min') }}
        </flux:button>

        <flux:button.group>
            <flux:button
                size="sm"
                variant="{{ $complexity === 'simple' ? 'primary' : 'filled' }}"
                icon="signal-cellular-1"
                wire:click="setComplexity('simple')"
                tooltip="{{ Complexity::Simple->label() }}"
            />
            <flux:button
                size="sm"
                variant="{{ $complexity === 'normal' ? 'primary' : 'filled' }}"
                icon="signal-cellular-2"
                wire:click="setComplexity('normal')"
                tooltip="{{ Complexity::Normal->label() }}"
            />
            <flux:button
                size="sm"
                variant="{{ $complexity === 'difficult' ? 'primary' : 'filled' }}"
                icon="signal-cellular-3"
                wire:click="setComplexity('difficult')"
                tooltip="{{ Complexity::Difficult->label() }}"
            />
        </flux:button.group>

        <div class="flex items-center gap-4">
            <flux:button.group>
                @if ($category)
                    <flux:button size="sm" variant="{{ $category ? 'primary' : 'filled' }}" icon:trailing="x-mark" wire:click="setCategory(null)">
                        {{ $categories->find($category)->name }}
                    </flux:button>
                @else
                    <flux:dropdown>
                        <flux:button size="sm" variant="filled" icon:trailing="chevron-down">
                            {{ __('All categories') }}
                        </flux:button>

                        <flux:menu>
                            <flux:menu.radio.group @change="$wire.setCategory($event.target.value)" value="{{ $category }}">
                                @foreach ($categories as $c)
                                    <flux:menu.radio value="{{ $c->id }}" :checked="$c->id == $category">
                                        {{ $c->name }}
                                    </flux:menu.radio>
                                @endforeach
                            </flux:menu.radio.group>
                        </flux:menu>
                    </flux:dropdown>
                @endif
            </flux:button.group>

            <flux:input
                wire:model.live.debounce="search"
                size="sm"
                placeholder="{{ __('Search for recipes...') }}"
                class="max-w-64"
            />
        </div>
    </div>

    <div class="grid gap-12 grid-cols-1 xs:grid-cols-2 lg:grid-cols-3">
        @foreach($recipes as $recipe)
            <a href="{{ route('recipes.show', $recipe) }}" class="space-y-4 group">
                @if ($recipe->photos->isNotEmpty())
                    <div class="overflow-hidden aspect-square rounded-xl">
                        <img
                            src="{{ $recipe->photos->first()->url() }}?v={{ $recipe->updated_at->timestamp }}"
                            alt="{{ $recipe->name }}"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                            loading="{{ $loop->index >= 6 ? 'lazy' : 'eager' }}"
                        >
                    </div>
                @endif

                <div class="space-y-2">
                    <flux:heading level="3" size="xl">{{ $recipe->name }}</flux:heading>

                    @if ($recipe->ratings_count)
                        <div class="flex items-center gap-2">
                            <div class="flex items-center gap-0.5">
                                @for ($i = 0; $i < $recipe->stars; $i++)
                                    <flux:icon.star class="size-3" variant="solid" />
                                @endfor

                                @for ($i = $recipe->stars; $i < 5; $i++)
                                    <flux:icon.star class="size-3" />
                                @endfor
                            </div>

                            <flux:text size="xs">
                                ({{ __(':stars stars / :ratings ratings', [
                                    'stars' => $recipe->stars,
                                    'ratings' => $recipe->ratings_count
                                ]) }})
                            </flux:text>
                        </div>
                    @endif

                    <div class="flex items-center gap-3">
                        @if ($recipe->preparation_time)
                            <flux:badge variant="pill" icon="clock">
                                {{ $recipe->preparation_time->format('H:i') }}
                            </flux:badge>
                        @endif

                        <flux:badge variant="pill" icon="{{ $recipe->complexity->icon() }}" color="{{ $recipe->complexity->color() }}">
                            {{ $recipe->complexity->label() }}
                        </flux:badge>
                    </div>

                    @if ($recipe->photos->isEmpty())
                        <flux:text class="mt-4">
                            {!! Str::limit(strip_tags($recipe->instructions), 500) !!}
                        </flux:text>
                    @endif
                </div>
            </a>
        @endforeach
    </div>

    @if ($hasMorePages)
        <div class="mt-12 flex justify-center">
            <flux:icon.arrow-path class="animate-spin" />

            <span
                x-intersect="$wire.loadMore()"
                class="absolute pointer-events-none -translate-y-[75vh]"
            ></span>
        </div>
    @endif
</section>
