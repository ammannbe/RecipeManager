<?php

namespace App\Models;

use App\Models\SlugifyTrait;
use App\Models\OrderByNameScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use SoftDeletes, SlugifyTrait, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'name_shortcut',
        'name_plural',
        'name_plural_shortcut'
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
     * Get the unit's ingredients
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany('\App\Models\Ingredient');
    }
}
