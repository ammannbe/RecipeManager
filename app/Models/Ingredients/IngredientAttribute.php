<?php

namespace App\Models\Ingredients;

use App\Models\SlugifyTrait;
use App\Models\OrderByNameScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class IngredientAttribute extends Model
{
    use SoftDeletes, SlugifyTrait, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'can_delete',
    ];

    /**
     * Relations that cascade or restrict on delete.
     *
     * @var array
     */
    protected $softCascade = [
        'ingredients@restrict'
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new OrderByNameScope);
    }

    /**
     * This ressource can be deleted
     *
     * @return bool
     */
    public function getCanDeleteAttribute(): bool
    {
        return !$this->ingredients()->exists();
    }

    /**
     * Get the ingredient-attribute's ingredients
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany('\App\Models\Ingredients\Ingredient');
    }
}
