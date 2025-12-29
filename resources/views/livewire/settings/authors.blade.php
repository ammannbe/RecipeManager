<?php

use App\Livewire\Traits\Sortable;
use App\Models\Author;
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

    /**
     * @var array<string|array<string>>
     */
    protected array $searchable = [
        'name',
        'user_email',
    ];

    /**
     * @var array<string>
     */
    protected array $sortable = [
        'name',
        'user_email',
        'user_admin',
    ];

    public function mount(): void
    {
        $this->defaultSortField('name');
        $this->defaultSortDirection('asc');
    }

    public function rendering(View $view): void
    {
        $view->layout('layouts.app');
        $view->title(__('Authors'));
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'authors' => Author::withAggregate('user', 'email')
                ->withAggregate('user', 'admin')
                ->search($this->searchable, $this->search)
                ->when(in_array($this->sortBy, $this->sortable), fn ($q) => $q->orderBy($this->sortBy, $this->sortDirection)->orderBy('id', 'desc'))
                ->paginate($this->pagination),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Authors')" :subheading="__('Manage application authors and users')">
        <flux:table :paginate="$authors">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'user_email'" :direction="$sortDirection" wire:click="sort('user_email')">{{ __('User email') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'user_admin'" :direction="$sortDirection" wire:click="sort('user_admin')">{{ __('Admin') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($authors as $author)
                    <flux:table.row>
                        <flux:table.cell>
                            {{ $author->name }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $author->user_email }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($author->user_admin)
                                <flux:badge variant="pill" size="sm" icon="check" color="green">
                                    {{ __('Yes') }}
                                </flux:badge>
                            {{-- @else
                                <flux:badge variant="pill" size="sm" icon="x-mark" color="red">
                                    {{ __('No') }}
                                </flux:badge> --}}
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-settings.layout>
</section>
