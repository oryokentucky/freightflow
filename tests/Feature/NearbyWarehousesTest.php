<?php

declare(strict_types=1);

use App\Models\Warehouse;

it('returns warehouses inside the radius, nearest first', function () {
    $klcc = ['latitude' => 3.1579, 'longitude' => 101.7123];

    $near = Warehouse::factory()->create(['name' => 'KL Central Hub', 'latitude' => 3.14, 'longitude' => 101.69]);
    $mid  = Warehouse::factory()->create(['name' => 'Shah Alam DC', 'latitude' => 3.07, 'longitude' => 101.52]);
    Warehouse::factory()->create(['name' => 'Penang Hub', 'latitude' => 5.41, 'longitude' => 100.33]); // ~300km away

    $results = Warehouse::query()->nearby($klcc['latitude'], $klcc['longitude'], 50)->get();

    expect($results)->toHaveCount(2)
        ->and($results[0]->id)->toBe($near->id)
        ->and($results[1]->id)->toBe($mid->id)
        ->and($results[0]->distance_km)->toBeLessThan($results[1]->distance_km);
});

it('excludes warehouses just outside the bounding box', function () {
    Warehouse::factory()->create(['latitude' => 3.60, 'longitude' => 101.71]); // ~49km north... adjust radius below

    $results = Warehouse::query()->nearby(3.1579, 101.7123, 30)->get();

    expect($results)->toHaveCount(0);
});
