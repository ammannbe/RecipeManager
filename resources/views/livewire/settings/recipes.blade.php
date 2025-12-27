<?php

use App\Livewire\Traits\Sortable;
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

    public int $pagination = 5;

    #[Url(as: 'search', history: true, keep: false)]
    public string $search = '';

    /**
     * @var array<string|array<string>>
     */
    protected array $searchable = [
        'name',
        'introduction',
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
        $this->defaultSortField('name');
        $this->defaultSortDirection('asc');
    }

    public function rendering(View $view): void
    {
        $view->layout('layouts.app');
        $view->title(__('Recipes'));
    }

    public function with(): array
    {
        return [
            'recipes' => user()->recipes()
                ->search($this->searchable, $this->search)
                ->when(in_array($this->sortBy, $this->sortable), fn ($q) => $q->orderBy($this->sortBy, $this->sortDirection)->orderBy('id', 'desc'))
                ->orderBy('name')
                ->paginate($this->pagination),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <flux:table :paginate="$recipes">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection">{{ __('Name') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'complexity'" :direction="$sortDirection">{{ __('Complexity') }}</flux:table.column>
                <flux:table.column>{{ __('Rating') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($recipes as $recipe)
                    <flux:table.row>
                        <flux:table.cell>
                            {{ $recipe->name }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $recipe->complexity->label() }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $recipe->stars }} ({{ $recipe->ratings_count }})
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-settings.layout>
</section>
