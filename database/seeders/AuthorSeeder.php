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
            Author::factory()
                ->create(['email' => config('mail.from.address')])
                ->each(function (Author $author) {
                    $user = User::factory()->make([
                        'name' => config('mail.from.name'),
                    ]);

                    return $author->user()->save($user);
                });
        }

        Author::factory(20)->create()->each(function (Author $author) {
            return $author->user()->save(User::factory()->make());
        });
    }
}
