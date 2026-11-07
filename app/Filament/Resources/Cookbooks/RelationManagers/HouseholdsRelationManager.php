<?php

namespace App\Filament\Resources\Cookbooks\RelationManagers;

use App\Models\Cookbook;
use App\Models\CookbookHouseholdShare;
use App\Models\Household;
use App\Services\CookbookSharing;
use Filament\Actions\Action;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Shares of the cookbook with households: the grants apply to every member of the household.
 */
class HouseholdsRelationManager extends RelationManager
{
    protected static string $relationship = 'households';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Shared with households');
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
     * Changing and removing a share follows the cookbook's share right, not the household's policy.
     */
    protected function getEditAuthorizationResponse(Model $record): Response
    {
        return $this->shareResponse();
    }

    protected function getDetachAuthorizationResponse(Model $record): Response
    {
        return $this->shareResponse();
    }

    private function shareResponse(): Response
    {
        return user()?->can('share', $this->cookbook()) ? Response::allow() : Response::deny();
    }

    private function cookbook(): Cookbook
    {
        /** @var Cookbook $cookbook */
        $cookbook = $this->getOwnerRecord();

        return $cookbook;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(MembersRelationManager::grantFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Household')),
                TextColumn::make('users_count')
                    ->label(__('Members'))
                    ->counts('users'),
                IconColumn::make('can_admin')->label(__('Administrate'))->boolean(),
                IconColumn::make('can_read')->label(__('Read'))->boolean(),
                IconColumn::make('can_create')->label(__('Create recipes'))->boolean(),
                IconColumn::make('can_update')->label(__('Edit recipes'))->boolean(),
                IconColumn::make('can_delete')->label(__('Delete recipes'))->boolean(),
            ])
            ->headerActions([
                Action::make('shareWithHousehold')
                    ->label(__('Share with household'))
                    ->icon(Heroicon::OutlinedHome)
                    ->modalHeading(__('Share with household'))
                    ->schema([
                        Select::make('household_id')
                            ->label(__('Household'))
                            // Only households the user belongs to.
                            ->options(fn (): array => Household::query()
                                ->withMember(user())
                                ->whereNotIn('id', $this->cookbook()->households()->pluck('households.id'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->helperText(__('Everyone in the household gets these permissions, also members who join later.'))
                            ->required(),
                        ...MembersRelationManager::grantFields(),
                    ])
                    ->action(function (array $data): void {
                        $household = Household::query()->whereKey($data['household_id'])->first();

                        if ($household === null || ! app(CookbookSharing::class)->shareWithHousehold($this->cookbook(), $household, $this->grantsFrom($data), user())) {
                            Notification::make()->danger()->title(__('You can only share with households you belong to.'))->send();

                            return;
                        }

                        Notification::make()->success()->title(__('Shared with the household'))->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('Permissions'))
                    ->modalHeading(__('Permissions'))
                    ->using(function (Household $record, array $data): Model {
                        app(CookbookSharing::class)->updateHouseholdShare($this->cookbook(), $record, $this->grantsFrom($data));

                        return $record;
                    }),
                DetachAction::make()
                    ->label(__('Unshare')),
            ])
            ->emptyStateHeading(__('Not shared with a household'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, bool>
     */
    private function grantsFrom(array $data): array
    {
        $grants = [];

        foreach (CookbookHouseholdShare::GRANTS as $grant) {
            $grants[$grant] = (bool) ($data[$grant] ?? false);
        }

        return $grants;
    }
}
