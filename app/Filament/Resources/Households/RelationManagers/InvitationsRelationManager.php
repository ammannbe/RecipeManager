<?php

namespace App\Filament\Resources\Households\RelationManagers;

use App\Models\HouseholdInvitation;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Pending invitations');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return user()?->can('manageMembers', $ownerRecord) ?? false;
    }

    protected function getDeleteAuthorizationResponse(Model $record): Response
    {
        return user()?->can('manageMembers', $this->getOwnerRecord()) ? Response::allow() : Response::deny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->modifyQueryUsing(fn ($query) => $query->pending())
            ->columns([
                TextColumn::make('email')
                    ->label(__('User email'))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('Role'))
                    ->formatStateUsing(fn (HouseholdInvitation $record): string => $record->role->label())
                    ->badge(),
                TextColumn::make('expires_at')
                    ->label(__('Expires at'))
                    ->dateTime(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label(__('Revoke')),
            ]);
    }
}
