<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Shipment;
use App\Services\Outbox\Outbox;
use Illuminate\Support\Facades\DB;

class DispatchShipment
{
    /**
     * @param array{reference: string} $args
     */
    public function __invoke(mixed $root, array $args): Shipment
    {
        return DB::transaction(function () use ($args) {
            /** @var Shipment $shipment */
            $shipment = Shipment::query()->where('reference', $args['reference'])->firstOrFail();

            $from = $shipment->status;
            $shipment->transitionTo('in_transit');

            $shipment->events()->create([
                'from_status' => $from,
                'to_status' => 'in_transit',
                'occurred_at' => now(),
            ]);

            Outbox::write('shipment.dispatched', [
                'shipment_id' => $shipment->id,
                'reference' => $shipment->reference,
            ]);

            return $shipment;
        });
    }
}
