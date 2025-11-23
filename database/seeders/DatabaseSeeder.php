<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            RoleAndPermissionSeeder::class,
        ]);

        // Always run BasicDataSeeder (creates minimal data without Faker)
        $this->call([
            BasicDataSeeder::class,
        ]);

        // Only run DummyDataSeeder in non-production environments
        // (it requires Faker which is a dev dependency)
        if (app()->environment(['local', 'testing', 'development'])) {
            $this->call([
                DummyDataSeeder::class,
            ]);
        }
    }
}