<?php

namespace App\Filament\Resources\Households\Pages;

use App\Filament\Resources\Households\HouseholdResource;
use App\Filament\Resources\Households\Widgets\PendingHouseholdInvitations;
use App\Households\AccountManagerHouseholds;
use App\Households\AccountManagerLabel;
use App\Services\Households;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ListHouseholds extends ListRecords
{
    protected static string $resource = HouseholdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageInAccountManager')
                ->label(__('Manage households in “:name”', ['name' => AccountManagerLabel::get()]))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(fn (): ?string => AccountManagerHouseholds::portalUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => AccountManagerHouseholds::portalUrl() !== null),
            CreateAction::make()
                // The creator becomes the first admin.
                ->using(fn (array $data): Model => app(Households::class)->create(user(), (string) $data['name'])),
        ];
    }

    public function getSubheading(): ?string
    {
        return Households::isLocal()
            ? null
            : (string) __('Households are managed in “:name”. Here they are read-only.', ['name' => AccountManagerLabel::get()]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PendingHouseholdInvitations::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
