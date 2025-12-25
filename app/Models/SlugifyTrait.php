<?php

namespace App\Models;

trait SlugifyTrait
{
    /**
     * Slugify the name and set the slug attribute
     *
     * @return void
     */
    public function slugifyName(): void
    {
        $this->slugify();
    }

    /**
     * Slugify the attribute and set another one
     *
     * @param  string  $attribute
     * @param  string  $setAttribute
     * @return void
     */
    public function slugify(string $attribute = 'name', string $setAttribute = 'slug'): void
    {
        $slug = $this->{$setAttribute} = \Str::slug($this->{$attribute});

        if (!$this->isDirty('slug')) {
            return;
        }

        for ($i = 1; static::withTrashed()->whereSlug($this->{$setAttribute})->exists(); $i++) {
            $this->{$setAttribute} = "{$slug}-{$i}";
        }
    }
}
