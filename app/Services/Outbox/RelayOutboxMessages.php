<?php

declare(strict_types=1);

namespace App\Services\Outbox;

use App\Jobs\DispatchDomainEvent;
use Illuminate\Support\Facades\DB;

/**
 * Outbox relay — run every minute via the scheduler (see routes/console.php):
 *
 *   Schedule::call(new RelayOutboxMessages())->everyMinute()->withoutOverlapping();
 *
 * Claims undispatched rows with a lock, pushes them to the queue, then
 * marks them dispatched. message_id gives consumers an idempotency key,
 * so at-least-once delivery is safe.
 */
final class RelayOutboxMessages
{
    private const BATCH = 100;

    public function __invoke(): int
    {
        $relayed = 0;

        DB::transaction(function () use (&$relayed) {
            $messages = DB::table('outbox_messages')
                ->whereNull('dispatched_at')
                ->where('attempts', '<', 5)
                ->orderBy('id')
                ->limit(self::BATCH)
                ->lockForUpdate()   // only the relay competes here, never user requests
                ->get();

            foreach ($messages as $message) {
                DispatchDomainEvent::dispatch(
                    $message->message_id,
                    $message->type,
                    json_decode($message->payload, true, 512, JSON_THROW_ON_ERROR),
                );

                DB::table('outbox_messages')
                    ->where('id', $message->id)
                    ->update([
                        'dispatched_at' => now(),
                        'attempts' => $message->attempts + 1,
                        'updated_at' => now(),
                    ]);

                $relayed++;
            }
        });

        return $relayed;
    }
}
