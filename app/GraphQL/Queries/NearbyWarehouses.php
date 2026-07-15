<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

class NearbyWarehouses
{
    /**
     * @param array{latitude: float, longitude: float, radiusKm: float} $args
     * @return Collection<int, Warehouse>
     */
    public function __invoke(mixed $root, array $args): Collection
    {
        return Warehouse::query()
            ->nearby($args['latitude'], $args['longitude'], $args['radiusKm'])
            ->limit(25)
            ->get();
    }
}
