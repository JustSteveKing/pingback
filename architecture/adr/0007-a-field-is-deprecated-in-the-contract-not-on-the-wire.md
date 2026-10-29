---
id: ADR-0007
title: A field is deprecated in the contract, not on the wire
status: accepted
created: 2026-10-29
decided: 2026-10-29
depends: [RFC-0001, ADR-0004]
updates: []
obsoletes: []
---

# ADR-0007: A field is deprecated in the contract, not on the wire

## Context

RFC-0001 planned deprecation as the first change after v1: a contact's single `phone` becoming a
list, "with `Deprecation` and `Sunset` on the old field and `Deprecations::strict()` turning the
SDK's tests red until it moves".

That plan doesn't survive the standards it names. RFC 9745 says the `Deprecation` header field
"applies to the resource identified with the response it occurred within" (section 2.2), and
says nothing about fields or parts of a representation. RFC 8594's `Sunset` is the same: the
resource becomes unresponsive. api-toolbox follows them, deprecating a controller or an action,
never a field. A `Deprecation` header on every contact response would tell every client, and
every generic tool that reads the header, that the contact resource itself is going away. It
isn't.

So HTTP has no header for "this field is going away". The places a field's deprecation can be
said truthfully are the contract, where OpenAPI 3 has `deprecated: true` on a schema property,
and the code clients compile against.

## Decision

`phone` is deprecated in the contract, not on the wire. Decided by Steve on 2026-10-29, together
with [ADR-0008](0008-the-organisation-filter-becomes-a-resource-and-is-deprecated.md), so that both
kinds of deprecation are shown.

- Contacts gain `phones`, a list of `{"number", "label"}`, label optional. `phone` stays, kept in
  step: reading it gives the first number or null, and writing it replaces the list with that one
  number. A request that sends both is a `422`.
- The OpenAPI document marks `phone` `deprecated: true`, its description pointing at `phones`,
  and the contract test checks the mark is there.
- The SDK marks `Contact::$phone` and its `with(phone:)` `@deprecated`, so static analysis and IDEs
  flag every use, and its own code and tests move to `phones`.
- Removal is a breaking change, so it waits for a v2 of the representation (a different ADR).
- No `Deprecation` or `Sunset` header for it: the resource isn't deprecated, and saying so would
  be wrong.

## Consequences

- RFC-0001's open question gets an honest answer, and a less tidy one than it planned:
  `Deprecations::strict()` has nothing to catch here, because the header was never the right
  signal for a field.
- That gap is itself the worked example for *Evolving an API*: a standard header with a precise
  scope, a change that falls outside it, and where the signal has to go instead.
- `Deprecation` and `Sunset` are exercised truthfully on a deprecated resource instead:
  [ADR-0008](0008-the-organisation-filter-becomes-a-resource-and-is-deprecated.md).
