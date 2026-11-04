<?php

namespace App\Filament\Widgets;

use App\Models\Food;
use App\Models\Recipe;
use App\Services\Season;
use Filament\Widgets\Widget;

/**
 * What is in season this month and visible recipes using it, own ones first.
 */
class SeasonWidget extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.season';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $month = Season::currentMonth();
        $user = user();

        return [
            'month' => Season::monthName($month),
            'foods' => Food::query()
                ->whereNotNull('season_months')
                ->whereRaw('json_contains(season_months, ?)', [(string) $month])
                ->whereHas('ingredients')
                ->orderBy('name')
                ->pluck('name'),
            'recipes' => Recipe::query()
                ->visibleTo($user)
                ->inSeason($month)
                ->addSelect(['recipes.*', 'season_foods_count' => Recipe::seasonFoodsCount($month)])
                ->orderByRaw('author_id = ? desc', [(int) $user?->author_id])
                ->orderByDesc('season_foods_count')
                ->orderBy('name')
                ->limit(6)
                ->get(),
        ];
    }
}
