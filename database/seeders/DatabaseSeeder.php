<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\ShipmentFactory;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        ShipmentFactory::new()->count(10)->create();

        WarehouseFactory::new()->count(10)->create();
    }
}
