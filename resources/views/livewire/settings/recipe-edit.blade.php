<?php

use App\Livewire\Forms\RecipeForm;
use App\Livewire\Traits\Sortable;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use Sortable;
    use WithPagination;

    public ?Recipe $recipe = null;

    public RecipeForm $form;

    /** @var array<string, string> */
    public array $search = [
        'cookbook' => '',
        'category' => '',
    ];

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'cookbooks' => user()->admin
                ? Cookbook::orderBy('name')->get()
                : author()->cookbooks()->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ];
    }

    #[On('open-modal')]
    public function openModal(Recipe $recipe): void
    {
        $this->recipe = $recipe;

        $this->form->setValues($recipe);

        $this->modal('recipe-edit')->show();
    }

    public function createCookbook(): void
    {
        $cookbook = author()->cookbooks()->create([
            'name' => $this->search['cookbook'],
        ]);

        $this->form->cookbook_id = $cookbook->id;
    }

    public function createCategory(): void
    {
        $category = Category::create([
            'name' => $this->search['category'],
        ]);

        $this->form->category_id = $category->id;
    }
}; ?>

<flux:modal name="recipe-edit" class="w-200">
    <form wire:submit="update" class="space-y-3">
        <flux:heading size="lg">{{ __('Edit recipe') }}</flux:heading>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <flux:select
                wire:model="form.cookbook_id"
                variant="listbox"
                searchable
                label="{{ __('Cookbook') }}"
                placeholder="{{ __('Public')}}"
                clearable
            >
                <x-slot name="search">
                    <flux:select.search
                        wire:model="search.cookbook"
                        placeholder="{{ __('Search...')}}"
                        class="px-4"
                    />
                </x-slot>

                @foreach ($cookbooks as $cookbook)
                    <flux:select.option wire:key="{{ $cookbook->id }}" value="{{ $cookbook->id }}">
                        {{ $cookbook->name }}
                        @if ($cookbook->author_id !== author()->id)
                            ({{ $cookbook->author_name }})
                        @endif
                    </flux:select.option>
                @endforeach

                <flux:select.option.create wire:click="createCookbook" min-length="2">
                    {{ __('Create') }} "<span wire:text="search.cookbook"></span>"
                </flux:select.option.create>
            </flux:select>

            <flux:select
                wire:model="form.category_id"
                variant="listbox"
                searchable
                label="{{ __('Category') }}"
                placeholder="{{ __('Choose :item...', ['item' => __('category')])}}"
                required
            >
                <x-slot name="search">
                    <flux:select.search
                        wire:model="search.category"
                        placeholder="{{ __('Search...')}}"
                        class="px-4"
                    />
                </x-slot>

                @foreach ($categories as $category)
                    <flux:select.option wire:key="{{ $category->id }}" value="{{ $category->id }}">
                        {{ $category->name }}
                    </flux:select.option>
                @endforeach

                <flux:select.option.create wire:click="createCategory" min-length="2">
                    {{ __('Create') }} "<span wire:text="search.category"></span>"
                </flux:select.option.create>
            </flux:select>

        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <flux:input
                wire:model="form.name"
                label="{{ __('Name') }}"
                placeholder="{{ __('e.g. :example', ['example' => __('Spaghetti Bolognese')]) }}"
                required
            />
        </div>
    </form>
</flux:modal>
