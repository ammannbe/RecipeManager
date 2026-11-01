<?php

namespace App\Filament\Auth;

use App\Models\Author;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

class Register extends BaseRegister
{
    /**
     * The author is created before the user; roll it back if the user insert fails.
     */
    protected ?bool $hasDatabaseTransactions = true;

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

    /**
     * author_id is not mass assignable, so it is set explicitly.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $authorId = $data['author_id'];
        unset($data['author_id']);

        $user = new User($data);
        $user->forceFill(['author_id' => $authorId])->save();

        return $user;
    }
}
