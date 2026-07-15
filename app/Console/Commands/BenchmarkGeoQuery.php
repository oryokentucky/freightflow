<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Benchmarks the naive full-table Haversine vs the bounding-box
 * pre-filtered version. Seeds 100k rows on first run. Paste the output
 * table into the README.
 */
class BenchmarkGeoQuery extends Command
{
    protected $signature = 'bench:geo {--rows=100000} {--runs=20}';

    protected $description = 'Benchmark naive vs bounding-box Haversine warehouse lookup';

    public function handle(): int
    {
        $target = (int) $this->option('rows');
        $runs = (int) $this->option('runs');

        if (Warehouse::query()->count() < $target) {
            $this->info("Seeding up to {$target} warehouses (one-time)...");
            $existing = Warehouse::query()->count();
            for ($i = $existing; $i < $target; $i += 1000) {
                Warehouse::factory()->count(min(1000, $target - $i))->create();
            }
        }

        [$lat, $lng, $radius] = [3.1579, 101.7123, 50.0];

        $naive = fn () => DB::select(
            'SELECT id, (6371 * acos(least(1.0, cos(radians(?)) * cos(radians(latitude))
             * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))) AS d
             FROM warehouses HAVING d <= ? ORDER BY d LIMIT 25',
            [$lat, $lng, $lat, $radius],
        );

        $optimized = fn () => Warehouse::query()->nearby($lat, $lng, $radius)->limit(25)->get();

        $this->table(
            ['Approach', 'Avg (ms)', 'Min (ms)', 'Max (ms)'],
            [
                ['Naive full-table Haversine', ...$this->time($naive, $runs)],
                ['Bounding-box pre-filter', ...$this->time($optimized, $runs)],
            ],
        );

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function time(callable $fn, int $runs): array
    {
        $samples = [];
        for ($i = 0; $i < $runs; $i++) {
            $start = hrtime(true);
            $fn();
            $samples[] = (hrtime(true) - $start) / 1e6;
        }

        return [
            number_format(array_sum($samples) / count($samples), 2),
            number_format(min($samples), 2),
            number_format(max($samples), 2),
        ];
    }
}
