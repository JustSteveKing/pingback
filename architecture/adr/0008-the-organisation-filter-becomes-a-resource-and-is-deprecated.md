---
id: ADR-0008
title: The organisation filter becomes a resource, and the old one is deprecated
status: accepted
created: 2026-10-29
decided: 2026-10-29
depends: [RFC-0001, ADR-0007]
updates: []
obsoletes: []
---

# ADR-0008: The organisation filter becomes a resource, and the old one is deprecated

## Context

[ADR-0007](0007-a-field-is-deprecated-in-the-contract-not-on-the-wire.md) found that RFC 9745's
`Deprecation` header is about resources, not fields, so the `phone` change can't carry it. RFC-0001
still wants the header and RFC 8594's `Sunset` shown end to end, with `Deprecations::strict()`
failing the SDK's tests until it moves. That needs a resource that is genuinely being retired.

A URI with a query is its own resource (RFC 3986 section 3, the query is part of what
identifies it), so `GET /contacts?organization_id=X` is a different resource from
`GET /contacts`. The contacts at one organisation are also a natural sub-resource of it.

## Decision

- `GET /v1/organizations/{organization}/contacts` lists the contacts at one organisation, with the
  same search, trashed filter and `Link` pages as `GET /v1/contacts`. An organisation that is
  deleted still lists its contacts; another account's is a `404`.
- `GET /v1/contacts?organization_id=` is deprecated from 2026-10-29, with a sunset of
  2027-01-31T23:59:59Z. Its responses carry `Deprecation`, `Sunset`, and `Link` with
  `rel="deprecation"` pointing at this ADR. `GET /v1/contacts` without the parameter is not
  deprecated and carries none of them.
- After the sunset, the deprecated form answers `410 Gone`, as Kit retires any endpoint.
- api-toolbox finds notices by route, and both forms share one, so Pingback decorates its
  `DeprecationSource` to add a notice when the route is `v1.contacts.index` and the request has
  `organization_id`. The header middleware and Kit's sunset check both read the same source, so
  they agree.
- The SDK runs its contract suite with `Deprecations::strict()`, so a deprecated resource fails it,
  and `contacts()->all(at:)` moves to the new resource.

## Consequences

- `Deprecation` and `Sunset` now mean exactly what their RFCs say wherever Pingback sends them.
- api-toolbox has no way to deprecate a query form of a route. Pingback's decorator is small, but
  it is a candidate for upstream.
