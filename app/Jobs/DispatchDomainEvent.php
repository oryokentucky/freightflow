<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Generic domain-event consumer entry point.
 *
 * Idempotent: the outbox relay guarantees at-least-once delivery, so we
 * dedupe on message_id with a cache lock before doing side effects
 * (notifications, webhooks, projections...).
 */
class DispatchDomainEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $messageId,
        public readonly string $type,
        public readonly array $payload,
    ) {
    }

    public function handle(): void
    {
        $key = "domain-event:{$this->messageId}";

        if (! Cache::add($key, true, now()->addDay())) {
            return; // already processed — duplicate delivery
        }

        match ($this->type) {
            'shipment.arrived' => $this->onShipmentArrived(),
            default => Log::warning("Unhandled domain event type: {$this->type}"),
        };
    }

    private function onShipmentArrived(): void
    {
        // e.g. notify consignee, trigger customs docs, update dashboard projection
        Log::info('Shipment arrived', $this->payload);
    }
}
