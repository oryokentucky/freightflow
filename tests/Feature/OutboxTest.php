<?php

declare(strict_types=1);

use App\Jobs\DispatchDomainEvent;
use App\Services\Outbox\Outbox;
use App\Services\Outbox\RelayOutboxMessages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('writes outbox rows inside the caller transaction', function () {
    try {
        DB::transaction(function () {
            Outbox::write('shipment.arrived', ['reference' => 'FF-2026-000001']);
            throw new RuntimeException('boom — simulate crash before commit');
        });
    } catch (RuntimeException) {
        // expected
    }

    // Rolled back with the transaction — no phantom event:
    expect(DB::table('outbox_messages')->count())->toBe(0);
});

it('relays undispatched messages to the queue exactly once', function () {
    Queue::fake();

    Outbox::write('shipment.arrived', ['reference' => 'FF-2026-000002']);

    $relayed = (new RelayOutboxMessages())();
    expect($relayed)->toBe(1);
    Queue::assertPushed(DispatchDomainEvent::class, 1);

    // Second run: nothing left to relay.
    expect((new RelayOutboxMessages())())->toBe(0);
});
