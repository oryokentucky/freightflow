<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property float $latitude
 * @property float $longitude
 */
class Warehouse extends Model
{
    use HasFactory;
    
    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Find warehouses within $radiusKm of a point, nearest first.
     *
     * Strategy: a cheap bounding-box WHERE clause (which can use the
     * (latitude, longitude) composite index) prunes ~99% of rows BEFORE
     * the expensive trigonometric Haversine runs. On 100k rows this
     * takes the query from ~400ms to single-digit ms.
     */
    public function scopeNearby(Builder $query, float $lat, float $lng, float $radiusKm = 50.0): Builder
    {
        // 1 degree of latitude ≈ 111.045 km everywhere.
        $latDelta = $radiusKm / 111.045;

        // 1 degree of longitude shrinks with latitude.
        $lngDelta = $radiusKm / (111.045 * max(cos(deg2rad($lat)), 1e-6));

        $cos = 'cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))';

        $haversine = sprintf(
            '(%f * acos(CASE WHEN (%s) > 1 THEN 1.0 ELSE (%s) END))',
            self::EARTH_RADIUS_KM,
            $cos,
            $cos,
        );

        return $query
            // Cheap, index-friendly pre-filter:
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            // Exact distance only on surviving rows:
            ->selectRaw("*, {$haversine} AS distance_km", [$lat, $lng, $lat, $lat, $lng, $lat])
            ->whereRaw("({$haversine}) <= ?", [$lat, $lng, $lat, $lat, $lng, $lat, $radiusKm])
            ->orderBy('distance_km');
    }
}
