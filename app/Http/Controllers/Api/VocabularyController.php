<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Unit;
use App\Services\RecipeImport\RecipeJsonSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class VocabularyController extends Controller
{
    /**
     * Known categories, units and complexities, so a companion app can write recipe drafts that
     * need little matching in the import wizard.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Category::class);
        Gate::authorize('viewAny', Unit::class);

        return response()->json([
            'data' => [
                'categories' => Category::query()->orderBy('name')->pluck('name')->all(),
                'units' => Unit::query()->orderBy('name')->get()
                    ->map(fn (Unit $unit): string => $unit->name_shortcut ?? $unit->name)
                    ->unique()->values()->all(),
                'complexities' => RecipeJsonSchema::complexities(),
            ],
        ]);
    }
}
