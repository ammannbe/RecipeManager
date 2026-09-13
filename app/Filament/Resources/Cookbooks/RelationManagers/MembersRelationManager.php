<?php

namespace App\Filament\Resources\Cookbooks\RelationManagers;

use App\Models\Cookbook;
use App\Models\CookbookMembership;
use App\Services\CookbookSharing;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Shared with');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return user()?->can('share', $ownerRecord) ?? false;
    }

    /**
     * @return array<int, Toggle>
     */
    private static function grantFields(): array
    {
        return [
            Toggle::make('can_admin')
                ->label(__('Administrate'))
                ->helperText(__('May rename, publish, delete and share the cookbook.')),
            Toggle::make('can_read')
                ->label(__('Read'))
                ->default(true),
            Toggle::make('can_create')
                ->label(__('Create recipes')),
            Toggle::make('can_update')
                ->label(__('Edit recipes')),
            Toggle::make('can_delete')
                ->label(__('Delete recipes')),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::grantFields());
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
                IconColumn::make('can_admin')->label(__('Administrate'))->boolean(),
                IconColumn::make('can_read')->label(__('Read'))->boolean(),
                IconColumn::make('can_create')->label(__('Create recipes'))->boolean(),
                IconColumn::make('can_update')->label(__('Edit recipes'))->boolean(),
                IconColumn::make('can_delete')->label(__('Delete recipes'))->boolean(),
            ])
            ->headerActions([
                Action::make('share')
                    ->label(__('Share'))
                    ->modalHeading(__('Share cookbook'))
                    ->schema([
                        TextInput::make('email')
                            ->label(__('User email'))
                            ->email()
                            ->required(),
                        ...self::grantFields(),
                    ])
                    ->action(function (array $data): void {
                        /** @var Cookbook $cookbook */
                        $cookbook = $this->getOwnerRecord();

                        app(CookbookSharing::class)->share(
                            $cookbook,
                            (string) $data['email'],
                            $this->grantsFrom($data),
                            user(),
                        );

                        // Deliberately identical whether or not the address has an account.
                        Notification::make()
                            ->success()
                            ->title(__('Invitation sent'))
                            ->body(__('If the address can be reached, access has been granted or an invitation was sent.'))
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('Permissions'))
                    ->modalHeading(__('Permissions'))
                    ->using(function (Model $record, array $data): Model {
                        /** @var Cookbook $cookbook */
                        $cookbook = $this->getOwnerRecord();

                        $cookbook->members()->updateExistingPivot($record->getKey(), $this->grantsFrom($data));

                        return $record;
                    }),
                DetachAction::make()
                    ->label(__('Revoke')),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, bool>
     */
    private function grantsFrom(array $data): array
    {
        $grants = [];

        foreach (CookbookMembership::GRANTS as $grant) {
            $grants[$grant] = (bool) ($data[$grant] ?? false);
        }

        return $grants;
    }
}
