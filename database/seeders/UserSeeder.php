<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Author;
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
        User::factory()->times(50)->create()->each(function ($user) {
            $user->author()->save(Author::factory()->make());
        });
    }
}
