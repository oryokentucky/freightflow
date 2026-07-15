<?php

declare(strict_types=1);

namespace App\Services\Outbox;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Transactional outbox writer.
 *
 * MUST be called inside a DB transaction alongside the domain change.
 * The row commits (or rolls back) atomically with the business data,
 * eliminating the dual-write problem of "DB committed but queue push
 * failed" (or vice versa).
 */
final class Outbox
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function write(string $type, array $payload): void
    {
        DB::table('outbox_messages')->insert([
            'message_id' => (string) Str::uuid(),
            'type' => $type,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
