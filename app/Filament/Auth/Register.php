<?php

namespace App\Filament\Auth;

use App\Models\Author;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use SensitiveParameter;

class Register extends BaseRegister
{
    /**
     * Humans need longer than this to fill in the form; bots submit right away.
     */
    public const MIN_SECONDS_TO_SUBMIT = 3;

    /**
     * The author is created before the user; roll it back if the user insert fails.
     */
    protected ?bool $hasDatabaseTransactions = true;

    #[Locked]
    public int $formRenderedAt = 0;

    public function mount(): void
    {
        parent::mount();

        $this->formRenderedAt = now()->getTimestamp();
    }

    public function register(): ?RegistrationResponse
    {
        if ($this->looksLikeABot()) {
            throw ValidationException::withMessages([
                'data.email' => __('Registration failed. Please try again.'),
            ]);
        }

        return parent::register();
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getHoneypotFormComponent(),
            ]);
    }

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
     * Hidden from humans and assistive technology; bots tend to fill in every field.
     */
    protected function getHoneypotFormComponent(): Component
    {
        return TextInput::make('website')
            ->hiddenLabel()
            ->dehydrated(false)
            ->extraFieldWrapperAttributes([
                'aria-hidden' => 'true',
                'style' => 'position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;',
            ])
            ->extraInputAttributes([
                'tabindex' => '-1',
                'autocomplete' => 'off',
            ]);
    }

    protected function looksLikeABot(): bool
    {
        if (filled($this->data['website'] ?? null)) {
            return true;
        }

        return now()->getTimestamp() - $this->formRenderedAt < self::MIN_SECONDS_TO_SUBMIT;
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
