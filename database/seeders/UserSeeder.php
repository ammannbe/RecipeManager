<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (! User::whereEmail(config('mail.from.address'))->exists()) {
            User::factory()
                ->create(['email' => config('mail.from.address')])
                ->each(function (User $user) {
                    $author = Author::factory()->make([
                        'name' => config('mail.from.name'),
                    ]);

                    return $user->author()->save($author);
                });
        }

        User::factory(20)->create()->each(function (User $user) {
            return $user->author()->save(Author::factory()->make());
        });
    }
}
