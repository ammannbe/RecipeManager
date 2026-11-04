<?php

namespace App\Filament\Widgets;

use App\Ai\RecipeAssistant;
use App\Filament\Pages\ConnectedAiClients;
use App\Filament\Pages\ImportRecipe;
use App\Filament\Resources\Recipes\RecipeResource;
use Filament\Widgets\Widget;

/**
 * Shortcuts at the top of the dashboard.
 */
class QuickStartWidget extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.quick-start';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'links' => [
                ['label' => RecipeAssistant::allows(user()) ? __('Read recipe with AI') : __('Import recipe'), 'url' => ImportRecipe::getUrl(), 'icon' => 'heroicon-o-sparkles', 'primary' => true],
                ['label' => __('New recipe'), 'url' => RecipeResource::getUrl('create'), 'icon' => 'heroicon-o-plus', 'primary' => false],
                ['label' => __('Season calendar'), 'url' => route('recipes.season'), 'icon' => 'heroicon-o-calendar-days', 'primary' => false],
                ['label' => __('Connected AI clients'), 'url' => ConnectedAiClients::getUrl(), 'icon' => 'heroicon-o-cpu-chip', 'primary' => false],
            ],
        ];
    }
}
