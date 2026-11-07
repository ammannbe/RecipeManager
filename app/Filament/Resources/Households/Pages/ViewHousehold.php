<?php

namespace App\Filament\Resources\Households\Pages;

use App\Exceptions\LastHouseholdAdmin;
use App\Filament\Resources\Households\HouseholdResource;
use App\Households\AccountManagerHouseholds;
use App\Households\AccountManagerLabel;
use App\Models\Household;
use App\Services\Households;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ViewHousehold extends ViewRecord
{
    protected static string $resource = HouseholdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageInAccountManager')
                ->label(__('Manage in “:name”', ['name' => AccountManagerLabel::get()]))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): ?string => AccountManagerHouseholds::portalUrl(), shouldOpenInNewTab: true)
                ->visible(fn (Household $record): bool => $record->isMirrored() && AccountManagerHouseholds::portalUrl() !== null),
            EditAction::make()
                ->label(__('Rename'))
                ->modalHeading(__('Rename household'))
                ->using(function (Household $record, array $data): Model {
                    app(Households::class)->rename($record, (string) $data['name']);

                    return $record;
                }),
            Action::make('leave')
                ->label(__('Leave household'))
                ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('You lose access to everything shared with the household, and your own cookbooks are no longer shared with it. The cookbooks stay yours.'))
                ->visible(fn (Household $record): bool => user()?->can('leave', $record) ?? false)
                ->action(function (Household $record): void {
                    try {
                        app(Households::class)->leave($record, user());
                    } catch (LastHouseholdAdmin $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title(__('You left the household.'))->send();

                    $this->redirect(HouseholdResource::getUrl());
                }),
            DeleteAction::make()
                ->modalDescription(__('Members lose access to the cookbooks shared with the household. The cookbooks and recipes stay with their owners.'))
                ->using(function (Household $record): bool {
                    app(Households::class)->delete($record);

                    return true;
                }),
        ];
    }
}
