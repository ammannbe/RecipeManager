<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuthorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (! User::whereEmail(config('mail.from.address'))->exists()) {
            $author = Author::factory()->create([
                'name' => config('mail.from.name'),
            ]);

            User::factory()->create([
                'author_id' => $author->id,
                'email' => config('mail.from.address'),
                'admin' => true,
            ]);
        }

        Author::factory(20)
            ->create()
            ->each(fn (Author $author) => User::factory()->create(['author_id' => $author->id]));
    }
}
