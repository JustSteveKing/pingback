---
id: ADR-0005
title: Contact export is asynchronous
status: accepted
created: 2026-10-29
decided: 2026-10-29
depends: [RFC-0001]
updates: []
obsoletes: []
---

# ADR-0005: Contact export is asynchronous

## Context

Exporting every contact in an account can take longer than a request should. Ping CRM's reports
page was a placeholder, so there is no behaviour to keep.

## Decision

`POST /v1/exports` starts an export of the account's contacts to CSV. With
`Prefer: respond-async` (RFC 7240) it answers `202 Accepted` with a `Location` monitor and
`Retry-After`; the monitor answers `200` with progress while it runs and `303 See Other` to the
file when it is done. A client that does not prefer async waits for the export, up to a limit,
and gets `202` after that anyway. Starting an export takes an `Idempotency-Key`, so a retried
start does not run it twice. A finished export also sends an `export.completed` webhook.

## Consequences

- The SDK's `exports()->create()` uses sdk-toolbox's `await()`, which polls the monitor as fast as
  `Retry-After` allows and follows the `303`.
- Export files expire; the monitor answers `410 Gone` for an expired one.
