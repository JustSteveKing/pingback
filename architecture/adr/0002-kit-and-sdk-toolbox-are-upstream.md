---
id: ADR-0002
title: Kit and sdk-toolbox are upstream
status: accepted
created: 2026-10-29
decided: 2026-10-29
depends: [RFC-0001]
updates: []
obsoletes: []
---

# ADR-0002: Kit and sdk-toolbox are upstream

## Context

Pingback exists to show Kit, api-toolbox and sdk-toolbox in use. Every change it makes to their
code is one a reader has to tell apart from Pingback's own, and building it will find things in
them that are wrong or missing, as [Flagpole](https://github.com/JustSteveKing/flagpole) and
todoist-sdk did.

## Decision

`api/` is created from Kit and treats it as upstream, as Flagpole does: it adds to Kit's code
freely, changes it only where Pingback cannot work otherwise, and gives each such change its own
commit saying why. `sdk/` requires sdk-toolbox from Packagist and never copies its code. Anything
wrong or missing for every user of Kit, api-toolbox or sdk-toolbox is fixed there first, released,
and brought in.

## Consequences

- A gap Pingback finds becomes a fix everyone gets, not a workaround only Pingback has.
- Pingback can wait on a release. That is the cost, and it is the point.
