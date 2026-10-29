---
id: ADR-0001
title: One repository for the API and the SDK
status: accepted
created: 2026-10-29
decided: 2026-10-29
depends: [RFC-0001]
updates: []
obsoletes: []
---

# ADR-0001: One repository for the API and the SDK

## Context

Pingback is an API and the SDK that calls it ([RFC-0001](../rfc/0001-pingback-v1.md)). A real
company would usually ship them from two repositories, with the SDK released to Packagist and the
API deployed. Pingback's SDK is not released, and its value is in showing the two ends changing
together.

## Decision

One repository, with the API in `api/` and the SDK in `sdk/`. Each is a project in its own right,
with its own `composer.json`, checks and README. CI builds both, boots the API, and runs the SDK's
tests against it ([ADR-0006](0006-the-sdk-is-tested-against-the-running-api.md)).

## Consequences

- A change to the contract lands on both ends in one commit, and one CI run says whether they
  still agree.
- The SDK cannot be required from Packagist as it stands. If it is ever released, it moves to a
  repository of its own, or a subtree split publishes `sdk/`.
- `api/` stays recognisable as a Kit application, so Kit's guides still apply inside it.

## Alternatives considered

- **Two repositories.** Closer to how a company ships, and the SDK could be released. Rejected:
  keeping them in step would need a published API image and a cross-repository workflow, which is
  machinery the demo does not need.
