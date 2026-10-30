<?php

namespace App\Services\Lookup;

use App\Models\Food;
use App\Models\IngredientAttribute;
use App\Models\Tag;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Merges a food, unit, ingredient attribute or tag into another one of the same type (e.g. a
 * request into an existing entry): every use points to the target, the source is deleted.
 */
class LookupMerger
{
    public function merge(Model $source, Model $target): void
    {
        if ($source::class !== $target::class || $source->is($target)) {
            throw new InvalidArgumentException('Only two different entries of the same type can be merged.');
        }

        DB::transaction(function () use ($source, $target): void {
            $from = $source->getKey();
            $to = $target->getKey();

            match (true) {
                $source instanceof Food => DB::table('ingredients')->where('food_id', $from)->update(['food_id' => $to]),
                $source instanceof Unit => DB::table('ingredients')->where('unit_id', $from)->update(['unit_id' => $to]),
                $source instanceof IngredientAttribute => $this->mergePivot('ingredient_ingredient_attribute', 'ingredient_id', 'ingredient_attribute_id', $from, $to),
                $source instanceof Tag => $this->mergePivot('recipe_tag', 'recipe_id', 'tag_id', $from, $to),
                default => throw new InvalidArgumentException('Unsupported entry type.'),
            };

            // Pending requests of the target are settled by the merge too.
            if (method_exists($target, 'approve') && $source->getAttribute('requested_at') === null) {
                $target->approve();
            }

            $source->forceDelete();
        });
    }

    /**
     * Repoints pivot rows; a row whose owner already has the target is dropped instead.
     */
    private function mergePivot(string $table, string $owner, string $column, int|string $from, int|string $to): void
    {
        $having = DB::table($table)->where($column, $to)->pluck($owner);

        DB::table($table)->where($column, $from)->whereIn($owner, $having)->delete();
        DB::table($table)->where($column, $from)->update([$column => $to]);
    }
}
