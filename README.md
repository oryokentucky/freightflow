# FreightFlow

**Event-driven shipment tracking API** built with Laravel 11, GraphQL, and Redis — modeling real-world freight operations: bookings, warehouse arrivals, concurrent status transitions, and geo-based warehouse lookup.

![CI](https://github.com/YOUR_USERNAME/freightflow/actions/workflows/ci.yml/badge.svg)](https://github.com/YOUR_USERNAME/freightflow/actions)
![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)
![Coverage](https://img.shields.io/badge/coverage-70%25%2B-blue)

## Why this project exists

Most demo APIs ignore the problems that actually break production systems. FreightFlow is built around three of them:

| Problem | Solution | Where |
|---|---|---|
| Race conditions on concurrent status updates | Optimistic locking with version columns + retry | `app/GraphQL/Mutations/RecordArrival.php` |
| Slow geo queries at scale | Haversine with bounding-box pre-filter (spatial index friendly) | `app/Models/Warehouse.php` |
| Lost events when DB commits but queue push fails | Transactional outbox pattern | `app/Services/Outbox/` |

## Architecture

```
┌──────────┐   GraphQL    ┌─────────────┐   outbox    ┌───────────┐
│  Client   │ ───────────▶ │  Laravel API │ ──────────▶ │   Redis    │
└──────────┘              │  (Lighthouse)│   relay     │   Queue    │
                          └──────┬──────┘             └─────┬─────┘
                                 │                          │
                          ┌──────▼──────┐            ┌──────▼──────┐
                          │    MySQL     │            │   Workers    │
                          │ (outbox tbl) │            │ (notify etc) │
                          └─────────────┘            └─────────────┘
```

## Quickstart

```bash
git clone https://github.com/YOUR_USERNAME/freightflow.git
cd freightflow
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan migrate --seed
```

GraphQL playground: http://localhost:8000/graphiql

## Benchmarks

Nearest-warehouse lookup, 100k warehouse rows (see `tests/Benchmarks/`):

| Approach | Avg query time |
|---|---|
| Raw Haversine over full table | ~276.71 ms |
| Bounding-box pre-filter + Haversine | ~204.83 ms |

*(Re-run locally: `php artisan bench:geo` — numbers will vary by hardware. Replace with your own results.)*

## Concurrency guarantees

Status transitions use **optimistic locking**. Two clients recording arrival for the same shipment simultaneously: exactly one succeeds, the other receives a `StaleShipmentException` and retries against fresh state. Proven by test:

```bash
php artisan test --filter=ConcurrentArrivalTest
```

## Key design decisions

Documented as ADRs in [`docs/adr/`](docs/adr/):

1. [Why GraphQL over REST](docs/adr/001-graphql-over-rest.md)
2. [Why optimistic over pessimistic locking](docs/adr/002-optimistic-locking.md)
3. [Why a transactional outbox](docs/adr/003-transactional-outbox.md)

## Tech

Laravel 11 · Lighthouse GraphQL · MySQL 8 · Redis · Pest · PHPStan (level 8) · Pint · Docker · GitHub Actions · AWS (see [`docs/deployment.md`](docs/deployment.md))

## License

MIT
