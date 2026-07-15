<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Exceptions\InvalidTransitionException;
use App\Support\Exceptions\StaleShipmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $reference
 * @property string $status
 * @property int $lock_version
 */
class Shipment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'arrived_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /** Legal state machine. Anything not listed is rejected. */
    private const TRANSITIONS = [
        'booked'     => ['in_transit', 'cancelled'],
        'in_transit' => ['arrived', 'cancelled'],
        'arrived'    => ['delivered'],
        'delivered'  => [],
        'cancelled'  => [],
    ];

    public function originWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'origin_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class);
    }

    /**
     * Transition status with optimistic locking.
     *
     * The UPDATE includes `WHERE lock_version = ?`. If another request
     * changed the row after we read it, zero rows match, and we throw
     * StaleShipmentException so the caller can re-read and retry.
     * No SELECT ... FOR UPDATE, no blocked connections.
     *
     * @param array<string, mixed> $extra additional columns to set atomically
     */
    public function transitionTo(string $newStatus, array $extra = []): void
    {
        if (! in_array($newStatus, self::TRANSITIONS[$this->status] ?? [], true)) {
            throw new InvalidTransitionException(
                "Cannot transition shipment {$this->reference} from '{$this->status}' to '{$newStatus}'."
            );
        }

        $affected = static::query()
            ->whereKey($this->id)
            ->where('lock_version', $this->lock_version)
            ->update([
                'status' => $newStatus,
                'lock_version' => $this->lock_version + 1,
                ...$extra,
            ]);

        if ($affected === 0) {
            throw new StaleShipmentException(
                "Shipment {$this->reference} was modified concurrently. Re-read and retry."
            );
        }

        $this->status = $newStatus;
        $this->lock_version++;
        foreach ($extra as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
