<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'code' => 'WH-' . str_pad((string) fake()->unique()->numberBetween(1, 9999999), 7, '0', STR_PAD_LEFT),
            'name' => $this->faker->city().' Distribution Center',
            // Cluster around Peninsular Malaysia by default:
            'latitude' => $this->faker->randomFloat(7, 1.3, 6.5),
            'longitude' => $this->faker->randomFloat(7, 99.7, 104.3),
            'capacity_pallets' => $this->faker->numberBetween(200, 5000),
        ];
    }
}
