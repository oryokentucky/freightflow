<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'reference' => 'FF-2026-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'origin_warehouse_id' => Warehouse::factory(),
            'destination_warehouse_id' => Warehouse::factory(),
            'status' => 'booked',
            'lock_version' => 0,
            'weight_kg' => $this->faker->randomFloat(2, 1, 2000),
        ];
    }
}
