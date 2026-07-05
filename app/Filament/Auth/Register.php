<?php

namespace App\Filament\Auth;

use App\Models\Author;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

class Register extends BaseRegister
{
    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label(__('Name'))
            ->required()
            ->maxLength(50)
            ->unique(Author::class, 'name')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeRegister(array $data): array
    {
        $author = Author::query()->create([
            'name' => trim((string) $data['name']),
        ]);

        unset($data['name']);

        $data['author_id'] = $author->id;

        return $data;
    }
}
