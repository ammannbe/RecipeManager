<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Foods\FoodResource;
use App\Filament\Resources\IngredientAttributes\IngredientAttributeResource;
use App\Filament\Resources\Tags\TagResource;
use App\Filament\Resources\Units\UnitResource;
use App\Models\AiUsage;
use App\Models\Food;
use App\Models\IngredientAttribute;
use App\Models\McpGrant;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * For admins: what needs attention (requests, missing data) and how the app is used.
 */
class AdminOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return (bool) user()?->isAdmin();
    }

    protected function getHeading(): ?string
    {
        return __('Administration');
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $requests = [
            [Food::class, FoodResource::class, __('Foods')],
            [Unit::class, UnitResource::class, __('Units')],
            [IngredientAttribute::class, IngredientAttributeResource::class, __('Ingredient attributes')],
            [Tag::class, TagResource::class, __('Tags')],
        ];

        $open = collect($requests)->map(fn (array $entry): array => [...$entry, $entry[0]::query()->requested()->count()]);
        $first = $open->first(fn (array $entry): bool => $entry[3] > 0) ?? $open->first();
        $total = $open->sum(fn (array $entry): int => $entry[3]);

        $usedFoods = Food::query()->whereHas('ingredients');
        $month = AiUsage::query()->where('created_at', '>=', now()->startOfMonth());

        return [
            Stat::make(__('Open requests'), (string) $total)
                ->description($open->filter(fn (array $entry): bool => $entry[3] > 0)->map(fn (array $entry): string => "{$entry[2]}: {$entry[3]}")->implode(' · ') ?: __('Nothing to approve'))
                ->color($total > 0 ? 'warning' : 'success')
                ->url($first[1]::getUrl('index', ['filters' => ['requested' => ['value' => 1]]])),
            Stat::make(__('Foods without conversion data'), (string) (clone $usedFoods)->whereNull('measure')->whereDoesntHave('unitWeights')->count())
                ->description(__('Used in recipes; :count without season data', ['count' => (clone $usedFoods)->whereNull('season_months')->count()]))
                ->url(FoodResource::getUrl('index', ['filters' => ['conversion_data' => ['value' => 0]]])),
            Stat::make(__('New users (30 days)'), (string) User::query()->where('created_at', '>=', now()->subDays(30))->count())
                ->description(__(':count users in total, :unverified unverified', [
                    'count' => User::query()->count(),
                    'unverified' => User::query()->whereNull('email_verified_at')->count(),
                ]))
                ->url(AuthorResource::getUrl('index')),
            Stat::make(__('AI requests this month'), (string) (clone $month)->count())
                ->description(__(':tokens tokens, :images images', [
                    'tokens' => Number::abbreviate((int) (clone $month)->sum('input_tokens') + (int) (clone $month)->sum('output_tokens')),
                    'images' => (int) (clone $month)->sum('images'),
                ])),
            Stat::make(__('Connected AI clients'), (string) McpGrant::query()->count())
                ->description(__(':count used in the last 7 days', ['count' => McpGrant::query()->where('last_used_at', '>=', now()->subDays(7))->count()])),
        ];
    }
}
