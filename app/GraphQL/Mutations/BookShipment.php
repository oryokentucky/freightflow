<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Shipment;
use App\Models\Warehouse;
use App\Services\Outbox\Outbox;
use Illuminate\Support\Facades\DB;

class BookShipment
{
    /**
     * @param array{originWarehouseCode: string, destinationWarehouseCode: string, weightKg: float} $args
     */
    public function __invoke(mixed $root, array $args): Shipment
    {
        return DB::transaction(function () use ($args) {
            $origin = Warehouse::query()->where('code', $args['originWarehouseCode'])->firstOrFail();
            $destination = Warehouse::query()->where('code', $args['destinationWarehouseCode'])->firstOrFail();

            $shipment = Shipment::query()->create([
                'reference' => self::nextReference(),
                'origin_warehouse_id' => $origin->id,
                'destination_warehouse_id' => $destination->id,
                'status' => 'booked',
                'weight_kg' => $args['weightKg'],
            ]);

            $shipment->events()->create([
                'from_status' => null,
                'to_status' => 'booked',
                'occurred_at' => now(),
            ]);

            Outbox::write('shipment.booked', [
                'shipment_id' => $shipment->id,
                'reference' => $shipment->reference,
            ]);

            return $shipment;
        });
    }

    /**
     * Gap-free-ish running number. A dedicated counter row is locked for
     * the duration of the (short) transaction — contention is acceptable
     * at booking volumes; at higher volume, switch to a Redis sequence.
     */
    private static function nextReference(): string
    {
        $year = now()->format('Y');
        $count = Shipment::query()->whereYear('created_at', $year)->lockForUpdate()->count();

        return sprintf('FF-%s-%06d', $year, $count + 1);
    }
}
