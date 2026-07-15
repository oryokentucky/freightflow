<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Shipment;
use App\Services\Outbox\Outbox;
use App\Support\Exceptions\StaleShipmentException;
use Illuminate\Support\Facades\DB;

/**
 * Records a shipment's arrival at its destination warehouse.
 *
 * Design notes (interview talking points):
 *
 * 1. OPTIMISTIC LOCKING — two warehouse staff scanning the same shipment
 *    within milliseconds of each other must not produce a double
 *    "arrived" event. transitionTo() guards with lock_version; the loser
 *    re-reads and (on retry) gets a clean InvalidTransitionException
 *    because the shipment is already 'arrived'.
 *
 * 2. TRANSACTIONAL OUTBOX — the domain change, audit event, and outbound
 *    message commit atomically. If the app crashes after commit, the
 *    outbox relay still delivers the message. No dual-write problem.
 *
 * 3. BOUNDED RETRY — one retry against fresh state, then surface the
 *    conflict to the client. Infinite retry loops hide real contention.
 */
class RecordArrival
{
    private const MAX_ATTEMPTS = 2;

    /**
     * @param array{reference: string, occurredAt?: string} $args
     */
    public function __invoke(mixed $root, array $args): Shipment
    {
        $attempts = 0;

        while (true) {
            $attempts++;

            /** @var Shipment $shipment */
            $shipment = Shipment::query()
                ->where('reference', $args['reference'])
                ->firstOrFail();

            try {
                return DB::transaction(function () use ($shipment, $args) {
                    $occurredAt = isset($args['occurredAt'])
                        ? now()->parse($args['occurredAt'])
                        : now();

                    $from = $shipment->status;

                    $shipment->transitionTo('arrived', [
                        'arrived_at' => $occurredAt,
                    ]);

                    $shipment->events()->create([
                        'from_status' => $from,
                        'to_status' => 'arrived',
                        'occurred_at' => $occurredAt,
                    ]);

                    Outbox::write('shipment.arrived', [
                        'shipment_id' => $shipment->id,
                        'reference' => $shipment->reference,
                        'arrived_at' => $occurredAt->toIso8601String(),
                    ]);

                    return $shipment;
                });
            } catch (StaleShipmentException $e) {
                if ($attempts >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
                // Loop: re-read fresh state and try once more.
            }
        }
    }
}
