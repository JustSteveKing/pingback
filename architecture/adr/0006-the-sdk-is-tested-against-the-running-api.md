---
id: ADR-0006
title: The SDK is tested against the running API
status: accepted
created: 2026-10-29
decided: 2026-10-29
depends: [RFC-0001, ADR-0001]
updates: []
obsoletes: []
---

# ADR-0006: The SDK is tested against the running API

## Context

The point of Pingback is that the two ends agree because they implement the same standards
([RFC-0001](../rfc/0001-pingback-v1.md)). A demo that only asserts that is not evidence; an SDK
tested only against hand-written fakes can drift from the API without a test failing.

## Decision

The SDK has two suites. Its unit suite uses sdk-toolbox's `FakeClient` and `FakeTime`, as any SDK
should. Its contract suite runs the SDK against the real API: CI boots `api/` with seeded data,
and the suite exercises every endpoint through the SDK, including the failure paths (a stale
`If-Match`, a missing ability, a retried create, an exhausted rate limit). sdk-toolbox's
`Recorder` captures the responses as fixtures, so the unit suite's fakes come from the real API
rather than from memory.

## Consequences

- A change to the API that the SDK does not follow fails CI on the same commit.
- Fixtures are re-recorded deliberately (`SDK_FIXTURES=record`), never by hand, and the recorder
  masks the API key in them.
- The contract suite needs the API running, so it is the slower suite and runs in CI and on
  demand, not on every save.
