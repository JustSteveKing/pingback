---
id: ADR-0004
title: Updates are merge patch, and require If-Match
status: accepted
created: 2026-10-29
decided: 2026-10-29
depends: [RFC-0001]
updates: []
obsoletes: []
---

# ADR-0004: Updates are merge patch, and require If-Match

## Context

A CRM record is edited by more than one person and by integrations, and a lost update (two
people save, the second silently overwrites the first) is the failure a CRM can least afford.
Ping CRM updates with a full `PUT` and no concurrency control.

## Decision

Organisations and contacts are updated with `PATCH` and `application/merge-patch+json`
(RFC 7396). Every resource carries a strong ETag, and an update must send `If-Match` (RFC 9110
§13.1.1): a stale one gets `412`, a missing one `428` (RFC 6585). There is no `PUT`. Delete takes
`If-Match` too, under the same rule.

## Consequences

- The SDK reads the ETag with each record and sends it back on update and delete, and builds the
  patch with `MergePatch::diff()`, so only changed fields travel.
- A client that wants to overwrite regardless has to fetch first. That is deliberate.
- In merge patch, `null` removes a member. Pingback's fields are either nullable or required,
  so `null` in a patch always means "clear this field", and on a required field it is a `422`.
