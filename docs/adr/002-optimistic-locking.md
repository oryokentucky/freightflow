# ADR 002: Optimistic over pessimistic locking for status transitions

**Status:** Accepted · **Date:** 2026-07

## Context

Two warehouse staff can scan the same shipment within milliseconds, both
firing `recordArrival`. Without protection this produces duplicate events
and corrupt state history.

## Options

1. **Pessimistic** — `SELECT ... FOR UPDATE` on the shipment row.
2. **Optimistic** — a `lock_version` column checked in the UPDATE's WHERE.

## Decision

Optimistic locking (option 2).

Conflicts are rare (two scans of the same parcel in the same instant), so
holding row locks across the whole request — blocking connections and
risking deadlocks with other transactions touching shipments — costs more
than it saves. Under optimistic locking the loser detects staleness
(`affected rows = 0`), re-reads, and either retries or receives a clean
`InvalidTransitionException` because the work is already done.

## Consequences

- Every state-changing write must go through `Shipment::transitionTo()`.
  Direct `->update(['status' => ...])` calls bypass the guard — enforced
  by convention and covered by `ConcurrentArrivalTest`.
- Callers need bounded retry logic (see `RecordArrival::MAX_ATTEMPTS`).
