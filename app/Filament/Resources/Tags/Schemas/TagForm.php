<?php

namespace App\Filament\Resources\Tags\Schemas;

use App\Models\Tag;
use App\Services\RecipeImport\RecipeJsonSchema;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(RecipeJsonSchema::MAX_TAG_NAME)
                    ->unique(Tag::class, 'name', ignoreRecord: true),
            ]);
    }
}
