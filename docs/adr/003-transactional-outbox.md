# ADR 003: Transactional outbox for domain events

**Status:** Accepted · **Date:** 2026-07

## Context

When a shipment arrives we must (a) update the DB and (b) publish an event
(notify consignee, update projections). Doing both directly creates the
dual-write problem:

- DB commits, queue push fails → event silently lost.
- Queue push succeeds, DB rolls back → phantom notification.

## Decision

Write events to an `outbox_messages` table **inside the same transaction**
as the domain change. A scheduled relay claims undispatched rows and pushes
them to Redis. Consumers dedupe on `message_id` (idempotency key), making
at-least-once delivery safe.

## Consequences

- Events are delivered with up to ~1 minute latency (relay interval).
  Acceptable for notifications; not for anything real-time.
- The outbox table needs pruning (scheduled job deletes dispatched rows
  older than 7 days).
- Simpler than Debezium/CDC — right-sized for a single-service system, and
  the pattern upgrades cleanly to CDC later.
