<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;

class EditProfile extends BaseEditProfile
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getUser();

        $data['name'] = $user->author?->name;

        return $data;
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('name')
            ->label(__('Name'))
            ->required()
            ->maxLength(50)
            ->unique('authors', 'name', ignorable: function () {
                /** @var User $user */
                $user = $this->getUser();

                return $user->author;
            })
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $name = trim((string) ($data['name'] ?? ''));

        unset($data['name']);

        parent::handleRecordUpdate($record, $data);

        if ($record instanceof User && $record->author) {
            $record->author->update(['name' => $name]);
        }

        return $record;
    }
}
