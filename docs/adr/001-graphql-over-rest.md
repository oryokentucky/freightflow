# ADR 001: GraphQL over REST for the shipment API

**Status:** Accepted · **Date:** 2026-07

## Context

Clients (warehouse tablet app, consignee tracking page, ops dashboard) need
very different views of the same shipment data. A REST API would either
over-fetch (one fat endpoint) or sprawl (`/shipments/:id/with-events`,
`?include=...` conventions).

## Decision

Use GraphQL via Lighthouse. One schema, per-client field selection, and the
schema file doubles as always-current API documentation.

## Consequences

- Must actively guard against N+1 queries → Lighthouse dataloader batching
  on `@belongsTo`/`@hasMany`, verified in tests with query counting.
- Must cap query cost → `@paginate(maxCount: 100)` on all list fields and a
  query depth limit in `config/lighthouse.php`.
- HTTP caching is harder than REST (single POST endpoint) → acceptable; the
  hot read path (tracking page) gets an application-level cache instead.
