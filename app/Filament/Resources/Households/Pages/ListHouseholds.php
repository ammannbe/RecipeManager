<?php

namespace App\Filament\Resources\Households\Pages;

use App\Filament\Resources\Households\HouseholdResource;
use App\Filament\Resources\Households\Widgets\PendingHouseholdInvitations;
use App\Households\KontoHouseholds;
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
            Action::make('manageInKonto')
                ->label(__('Manage households in your account'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(fn (): ?string => KontoHouseholds::portalUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => KontoHouseholds::portalUrl() !== null),
            CreateAction::make()
                // The creator becomes the first admin.
                ->using(fn (array $data): Model => app(Households::class)->create(user(), (string) $data['name'])),
        ];
    }

    public function getSubheading(): ?string
    {
        return Households::isLocal()
            ? null
            : (string) __('Households are managed in the central account. Here they are read-only.');
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
