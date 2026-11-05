<?php

namespace App\Filament\Widgets;

use App\Models\AiUsage;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * For admins: AI usage per user this month (basis for billing by usage).
 */
class AiUsageWidget extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) user()?->admin;
    }

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('AI usage this month');
    }

    public function table(Table $table): Table
    {
        $month = fn (Builder $query): Builder => $query->where('created_at', '>=', now()->startOfMonth());

        return $table
            ->query(fn (): Builder => User::query()
                ->with('author')
                ->whereHas('aiUsages', $month)
                ->withCount(['aiUsages as requests' => $month])
                ->withSum(['aiUsages as input_tokens' => $month], 'input_tokens')
                ->withSum(['aiUsages as output_tokens' => $month], 'output_tokens')
                ->withSum(['aiUsages as images' => $month], 'images'))
            ->defaultSort('requests', 'desc')
            ->columns([
                TextColumn::make('author.name')->label(__('User'))->description(fn (User $record): string => $record->email),
                TextColumn::make('requests')->label(__('Requests'))->numeric()->sortable()
                    ->description(fn (User $record): string => $this->features($record)),
                TextColumn::make('input_tokens')->label(__('Input tokens'))->numeric(),
                TextColumn::make('output_tokens')->label(__('Output tokens'))->numeric(),
                TextColumn::make('images')->label(__('Images'))->numeric(),
            ])
            ->paginated([10])
            ->emptyStateHeading(__('No AI requests this month'));
    }

    /**
     * Requests per feature this month, e.g. "Recipe: 3 · Conversions: 1".
     */
    private function features(User $user): string
    {
        return $user->aiUsages()
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('feature, count(*) as total')
            ->groupBy('feature')
            ->orderByDesc('total')
            ->pluck('total', 'feature')
            ->map(fn (mixed $total, string $feature): string => AiUsage::featureLabel($feature).': '.$total)
            ->implode(' · ');
    }
}
