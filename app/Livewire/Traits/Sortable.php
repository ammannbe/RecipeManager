<?php

namespace App\Livewire\Traits;

use Livewire\Attributes\Url;

trait Sortable
{
    #[Url]
    public string $sortBy = 'id';

    #[Url]
    public string $sortDirection = 'asc';

    /** @var array<string> */
    protected array $queryString = ['sort'];

    protected ?string $onSorting = null;

    private function defaultSortField(string $field = 'id'): void
    {
        $this->sortBy = $field;
    }

    private function defaultSortDirection(string $sortDirection = 'asc'): void
    {
        $this->sortDirection = $sortDirection;
    }

    private function onSorting(string $method): void
    {
        $this->onSorting = $method;
    }

    public function sort(string $field): void
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }

        $this->sortBy = $field;

        if ($this->onSorting && method_exists($this, $this->onSorting)) {
            $this->{$this->onSorting}();
        }
    }
}
