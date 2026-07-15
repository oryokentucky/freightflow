<?php

declare(strict_types=1);

use App\Models\Shipment;
use App\Models\Warehouse;
use App\Support\Exceptions\InvalidTransitionException;
use App\Support\Exceptions\StaleShipmentException;

/**
 * Proves the optimistic locking guarantee: when two processes race to
 * transition the same shipment, exactly one wins.
 *
 * We simulate the race deterministically: load the same row into two
 * separate model instances (as two HTTP requests would), transition the
 * first, then assert the second — still holding the stale lock_version —
 * fails with StaleShipmentException.
 */

beforeEach(function () {
    $origin = Warehouse::factory()->create();
    $destination = Warehouse::factory()->create();

    $this->shipment = Shipment::factory()->create([
        'origin_warehouse_id' => $origin->id,
        'destination_warehouse_id' => $destination->id,
        'status' => 'in_transit',
        'lock_version' => 0,
    ]);
});

it('rejects a concurrent transition made with a stale lock version', function () {
    // Two "requests" read the same row at the same moment:
    $requestA = Shipment::query()->findOrFail($this->shipment->id);
    $requestB = Shipment::query()->findOrFail($this->shipment->id);

    // Request A wins the race:
    $requestA->transitionTo('arrived', ['arrived_at' => now()]);

    // Request B still holds lock_version 0 — its UPDATE must match zero rows:
    expect(fn () => $requestB->transitionTo('arrived', ['arrived_at' => now()]))
        ->toThrow(StaleShipmentException::class);

    // Exactly one arrival recorded:
    expect($this->shipment->fresh())
        ->status->toBe('arrived')
        ->lock_version->toBe(1);
});

it('rejects a retry after re-read because the transition is no longer legal', function () {
    $requestA = Shipment::query()->findOrFail($this->shipment->id);
    $requestA->transitionTo('arrived', ['arrived_at' => now()]);

    // The losing request re-reads fresh state (as RecordArrival's retry loop does):
    $requestB = Shipment::query()->findOrFail($this->shipment->id);

    // 'arrived' -> 'arrived' is not a legal transition — clean domain error, no double event:
    expect(fn () => $requestB->transitionTo('arrived', ['arrived_at' => now()]))
        ->toThrow(InvalidTransitionException::class);
});

it('increments lock_version on every successful transition', function () {
    $shipment = Shipment::query()->findOrFail($this->shipment->id);

    $shipment->transitionTo('arrived', ['arrived_at' => now()]);
    expect($shipment->fresh()->lock_version)->toBe(1);

    $shipment->transitionTo('delivered', ['delivered_at' => now()]);
    expect($shipment->fresh()->lock_version)->toBe(2);
});

it('rejects illegal state machine transitions outright', function () {
    $shipment = Shipment::query()->findOrFail($this->shipment->id);

    expect(fn () => $shipment->transitionTo('booked'))
        ->toThrow(InvalidTransitionException::class);
});
