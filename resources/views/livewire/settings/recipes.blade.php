<?php

use App\Enums\Complexity;
use App\Livewire\Traits\Sortable;
use App\Models\Author;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use Sortable;
    use WithPagination;

    public int $pagination = 15;

    #[Url(as: 'search', history: true, keep: false)]
    public string $search = '';

    #[Url(as: 'quick', history: true, keep: false)]
    public bool $quick = false;

    #[Url(as: 'complexity', history: true, keep: false)]
    public ?string $complexity = null;

    #[Url(as: 'author', history: true, keep: false)]
    public ?int $author = null;

    #[Url(as: 'cookbook', history: true, keep: false)]
    public ?int $cookbook = null;

    #[Url(as: 'category', history: true, keep: false)]
    public ?int $category = null;

    /**
     * @var array<string|array<string>>
     */
    protected array $searchable = [
        'name',
        'instructions',
        'preparation_time',
        'complexity',
        'author' => ['name'],
        'cookbook' => ['name'],
    ];

    /**
     * @var array<string>
     */
    protected array $sortable = [
        'name',
        'complexity',
        'author_name',
        'cookbook_name',
        'preparation_time',
        'created_at',
    ];

    public function mount(): void
    {
        $this->defaultSortField('name');
        $this->defaultSortDirection('asc');
    }

    public function rendering(View $view): void
    {
        $view->layout('layouts.app');
        $view->title(__('Recipes'));
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'recipes' => (user()->admin ? new Recipe : author()->recipes())
                ->withAggregate('author', 'name')
                ->withAggregate('cookbook', 'name')
                ->withCount('ratings')
                ->withAvg('ratings', 'stars')
                ->search($this->searchable, $this->search)
                ->when(in_array($this->sortBy, $this->sortable), fn ($q) => $q->orderBy($this->sortBy, $this->sortDirection)->orderBy('id', 'desc'))
                ->when($this->quick, fn ($q) => $q->where('preparation_time', '<=', '00:30:00'))
                ->when($this->complexity, fn ($q) => $q->where('complexity', $this->complexity))
                ->when($this->author, fn ($q) => $q->where('author_id', $this->author))
                ->when($this->cookbook, fn ($q) => $q->where('cookbook_id', $this->cookbook))
                ->when($this->category, fn ($q) => $q->where('category_id', $this->category))
                ->paginate($this->pagination),
            'categories' => Category::get(),
            'authors' => user()->admin ? Author::get() : null,
            'cookbooks' => user()->admin ? Cookbook::get() : author()->cookbooks(),
        ];
    }

    public function updated(string $property, mixed $value): void
    {
        if ($property === 'search') {
            $this->resetPage();
        }
    }

    public function setQuick(): void
    {
        $this->quick = ! $this->quick;
        $this->resetPage();
    }

    public function setComplexity(?string $complexity = null): void
    {
        $this->complexity = $this->complexity === $complexity ? null : $complexity;
        $this->resetPage();
    }

    public function setAuthor(?int $author = null): void
    {
        $this->author = $this->author === $author ? null : $author;
        $this->resetPage();
    }

    public function setCookbook(?int $cookbook = null): void
    {
        $this->cookbook = $this->cookbook === $cookbook ? null : $cookbook;
        $this->resetPage();
    }

    public function setCategory(?int $category = null): void
    {
        $this->category = $category;
        $this->resetPage();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
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

                @if (user()->admin)
                    <flux:button.group>
                        @if ($author)
                            <flux:button size="sm" variant="{{ $author ? 'primary' : 'filled' }}" icon:trailing="x-mark" wire:click="setAuthor(null)">
                                {{ $authors->find($author)->name }}
                            </flux:button>
                        @else
                            <flux:dropdown>
                                <flux:button size="sm" variant="filled" icon:trailing="chevron-down">
                                    {{ __('All authors') }}
                                </flux:button>

                                <flux:menu>
                                    <flux:menu.radio.group @change="$wire.setAuthor($event.target.value)" value="{{ $author }}">
                                        @foreach ($authors as $a)
                                            <flux:menu.radio value="{{ $a->id }}" :checked="$a->id == $author">
                                                {{ $a->name }}
                                            </flux:menu.radio>
                                        @endforeach
                                    </flux:menu.radio.group>
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </flux:button.group>
                @endif

                @if ($cookbooks->isNotEmpty())
                    <flux:button.group>
                        @if ($cookbook)
                            <flux:button size="sm" variant="{{ $cookbook ? 'primary' : 'filled' }}" icon:trailing="x-mark" wire:click="setCookbook(null)">
                                {{ $cookbooks->find($cookbook)->name }}
                            </flux:button>
                        @else
                            <flux:dropdown>
                                <flux:button size="sm" variant="filled" icon:trailing="chevron-down">
                                    {{ __('All cookbooks') }}
                                </flux:button>

                                <flux:menu>
                                    <flux:menu.radio.group @change="$wire.setCookbook($event.target.value)" value="{{ $cookbook }}">
                                        @foreach ($cookbooks as $c)
                                            <flux:menu.radio value="{{ $c->id }}" :checked="$c->id == $cookbook">
                                                {{ $c->name }}
                                            </flux:menu.radio>
                                        @endforeach
                                    </flux:menu.radio.group>
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </flux:button.group>
                @endif

                <flux:input
                    wire:model.live.debounce="search"
                    size="sm"
                    placeholder="{{ __('Search for recipes...') }}"
                    class="max-w-64"
                />
            </div>
        </div>

        <flux:table :paginate="$recipes">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'complexity'" :direction="$sortDirection" wire:click="sort('complexity')">{{ __('Complexity') }}</flux:table.column>
                @if (user()->admin)
                    <flux:table.column sortable :sorted="$sortBy === 'author_name'" :direction="$sortDirection" wire:click="sort('author_name')">{{ __('Author') }}</flux:table.column>
                @endif
                <flux:table.column sortable :sorted="$sortBy === 'cookbook_name'" :direction="$sortDirection" wire:click="sort('cookbook_name')">{{ __('Cookbook') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'preparation_time'" :direction="$sortDirection" wire:click="sort('preparation_time')">{{ __('Time') }}</flux:table.column>
                <flux:table.column>{{ __('Rating') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($recipes as $recipe)
                    <flux:table.row>
                        <flux:table.cell class="w-84">
                            <flux:link href="{{ route('recipes.show', ['recipe' => $recipe]) }}" target="_blank" rel="noopener noreferrer">
                                <div class="flex items-center gap-1">
                                    <span class="max-w-80 overflow-hidden text-ellipsis">{{ $recipe->name }}</span>
                                    <flux:icon.arrow-top-right-on-square variant="micro" />
                                </div>
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="pill" size="sm" icon="{{ $recipe->complexity->icon() }}" color="{{ $recipe->complexity->color() }}">
                                {{ $recipe->complexity->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        @if (user()->admin)
                            <flux:table.cell>
                                <flux:badge variant="pill" size="sm" icon="user-circle" color="{{ $recipe->author_id === author()->id ? 'blue' : '' }}">
                                    {{ $recipe->author_name }}
                                </flux:badge>
                            </flux:table.cell>
                        @endif
                        <flux:table.cell>
                            @if ($recipe->cookbook)
                                <flux:badge variant="pill" size="sm" icon="bookmark">
                                    {{ $recipe->cookbook_name }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($recipe->preparation_time)
                                <flux:badge variant="pill" size="sm" icon="clock">
                                    {{ $recipe->preparation_time->format('H:i') }}
                                </flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="space-y-1">
                                <div class="flex items-center gap-0.5">
                                    @for ($i = 0; $i < $recipe->ratings_stars; $i++)
                                        <flux:icon.star class="size-4" variant="solid" />
                                    @endfor

                                    @for ($i = $recipe->ratings_stars; $i < 5; $i++)
                                        <flux:icon.star class="size-4 text-amber-400" />
                                    @endfor
                                </div>

                                <flux:text class="leading-tight ml-px" size="sm">
                                    ({{ __(':stars stars / :ratings ratings', [
                                        'stars' => $recipe->ratings_stars ?? 0,
                                        'ratings' => $recipe->ratings_count
                                    ]) }})
                                </flux:text>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                icon="pencil-square"
                                icon:variant="outline"
                                size="sm"
                                variant="ghost"
                                wire:click="$dispatchTo('settings.recipe-edit', 'open-modal', { recipe: {{ $recipe->id }} })"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-settings.layout>

    @livewire('settings.recipe-edit')
</section>
