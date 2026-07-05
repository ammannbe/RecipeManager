<?php

namespace App\Filament\Resources\Recipes\Pages;

use App\Filament\Resources\Recipes\RecipeResource;
use App\Models\Recipe;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRecipe extends CreateRecord
{
    protected static string $resource = RecipeResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $recipe = new Recipe;
        $recipe->fill($data);
        $recipe->author_id = user()->author_id;
        $recipe->save();

        return $recipe;
    }
}
