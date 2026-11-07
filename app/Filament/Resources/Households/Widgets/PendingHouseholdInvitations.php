<?php

namespace App\Filament\Resources\Households\Widgets;

use App\Filament\Resources\Households\HouseholdResource;
use App\Models\HouseholdInvitation;
use App\Services\Households;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Invitations to households sent to the user's verified address, to accept or decline.
 */
class PendingHouseholdInvitations extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = user();

        return Households::isLocal()
            && $user?->hasVerifiedEmail()
            && HouseholdInvitation::query()->pending()->sentTo($user)->exists();
    }

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('Invitations for you');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => HouseholdInvitation::query()
                ->pending()
                ->when(user(), fn (Builder $query, $user) => $query->sentTo($user), fn (Builder $query) => $query->whereRaw('1 = 0'))
                ->with(['household', 'invitedBy']))
            ->columns([
                TextColumn::make('household.name')->label(__('Household')),
                TextColumn::make('invitedBy.author.name')->label(__('Invited by'))->placeholder('-'),
                TextColumn::make('role')
                    ->label(__('Role'))
                    ->formatStateUsing(fn (HouseholdInvitation $record): string => $record->role->label())
                    ->badge(),
                TextColumn::make('expires_at')->label(__('Expires at'))->dateTime(),
            ])
            ->recordActions([
                Action::make('accept')
                    ->label(__('Accept'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->action(function (HouseholdInvitation $record): void {
                        if (! app(Households::class)->accept($record, user())) {
                            Notification::make()->danger()->title(__('This invitation is no longer valid.'))->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title(__('You joined the household ":name".', ['name' => $record->household->name]))
                            ->send();

                        $this->redirect(HouseholdResource::getUrl('view', ['record' => $record->household]));
                    }),
                Action::make('decline')
                    ->label(__('Decline'))
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (HouseholdInvitation $record): void {
                        app(Households::class)->decline($record, user());

                        Notification::make()->success()->title(__('Invitation declined'))->send();

                        $this->redirect(HouseholdResource::getUrl());
                    }),
            ])
            ->paginated(false);
    }
}
