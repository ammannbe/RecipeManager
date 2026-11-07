<?php

namespace App\Filament\Resources\Households\RelationManagers;

use App\Enums\HouseholdRole;
use App\Exceptions\LastHouseholdAdmin;
use App\Models\Household;
use App\Models\User;
use App\Services\Households;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Members');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function household(): Household
    {
        /** @var Household $household */
        $household = $this->getOwnerRecord();

        return $household;
    }

    private function canManage(): bool
    {
        return user()?->can('manageMembers', $this->household()) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('author.name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('User email'))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('Role'))
                    ->state(fn (User $record): ?string => $this->roleOf($record)?->label())
                    ->badge()
                    ->color(fn (User $record): string => $this->roleOf($record) === HouseholdRole::Admin ? 'primary' : 'gray'),
                TextColumn::make('joined_at')
                    ->label(__('Joined'))
                    ->state(fn (User $record): mixed => $record->getRelationValue('pivot')?->joined_at)
                    ->date()
                    ->placeholder('-'),
            ])
            ->headerActions([
                Action::make('invite')
                    ->label(__('Invite'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->modalHeading(__('Invite to household'))
                    ->visible(fn (): bool => $this->canManage())
                    ->schema([
                        TextInput::make('email')
                            ->label(__('User email'))
                            ->email()
                            ->required(),
                        Select::make('role')
                            ->label(__('Role'))
                            ->options(HouseholdRole::options())
                            ->default(HouseholdRole::Member->value)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        abort_unless($this->canManage(), 403);

                        app(Households::class)->invite(
                            $this->household(),
                            (string) $data['email'],
                            HouseholdRole::from((string) $data['role']),
                            user(),
                        );

                        // Deliberately identical whether or not the address has an account.
                        Notification::make()
                            ->success()
                            ->title(__('Invitation sent'))
                            ->body(__('If the address can be reached, an invitation was sent.'))
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('role')
                    ->label(__('Change role'))
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->visible(fn (): bool => $this->canManage())
                    ->fillForm(fn (User $record): array => ['role' => $this->roleOf($record)?->value])
                    ->schema([
                        Select::make('role')
                            ->label(__('Role'))
                            ->options(HouseholdRole::options())
                            ->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        abort_unless($this->canManage(), 403);

                        $this->attempt(fn () => app(Households::class)->changeRole(
                            $this->household(),
                            $record,
                            HouseholdRole::from((string) $data['role']),
                        ), __('Saved'));
                    }),
                Action::make('remove')
                    ->label(__('Remove'))
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('The member loses access to the cookbooks shared with the household. Their own cookbooks stay theirs.'))
                    // Leaving yourself is the page's "Leave household".
                    ->visible(fn (User $record): bool => $this->canManage() && ! $record->is(user()))
                    ->action(function (User $record): void {
                        abort_unless($this->canManage(), 403);

                        $this->attempt(fn () => app(Households::class)->removeMember($this->household(), $record), __('Member removed'));
                    }),
            ]);
    }

    private function roleOf(User $record): ?HouseholdRole
    {
        $role = $record->getRelationValue('pivot')?->role;

        return $role instanceof HouseholdRole ? $role : HouseholdRole::tryFrom((string) $role);
    }

    private function attempt(callable $change, string $success): void
    {
        try {
            $change();
        } catch (LastHouseholdAdmin $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($success)->send();
    }
}
