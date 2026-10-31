<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Food;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FoodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Food::class);

        return response()->json([
            'data' => Food::query()
                ->visibleTo($request->user())
                ->orderBy('name')
                ->get(['id', 'name', 'season_months', 'storage_months'])
                ->map(fn (Food $food): array => [
                    'id' => $food->id,
                    'name' => $food->name,
                    'season_months' => $food->season_months ?? [],
                    'storage_months' => $food->storage_months ?? [],
                ])
                ->all(),
        ]);
    }
}
