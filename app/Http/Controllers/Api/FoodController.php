<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Food;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FoodController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Food::class);

        return response()->json([
            'data' => Food::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Food $food): array => ['id' => $food->id, 'name' => $food->name])
                ->all(),
        ]);
    }
}
