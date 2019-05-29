<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    public static function searchRecipes($name) {
        return self::where('name', 'LIKE', '%'.$name.'%')
            ->with(['recipes', 'recipes.author', 'recipes.category'])
            ->get();
    }

    public function recipes() {
        return $this->hasMany('\App\Models\Recipe');
    }
}
