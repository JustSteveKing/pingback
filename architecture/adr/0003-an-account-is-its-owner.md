---
id: ADR-0003
title: An account is its owner
status: accepted
created: 2026-10-29
decided: 2026-10-29
depends: [RFC-0001]
updates: []
obsoletes: []
---

# ADR-0003: An account is its owner

## Context

In Ping CRM an account has many users, one of them the owner, and everything belongs to the
account ([REF-0001](../ref/0001-ping-crm.md)). Kit gives every user registration, session tokens
and API keys with abilities. Managing several users per account adds endpoints, invitations and
roles, and no standard the demo is about.

## Decision

Registering creates a user and an account that user owns. In v1 an account has exactly one user,
and everything Pingback stores belongs to an account. Access is by Kit's tokens: every route
checks an ability (`organizations:read`, `contacts:write`, `exports:create` and so on), so an API
key can be limited to what a script or integration needs, and a key without the ability gets
Kit's `403` problem.

## Consequences

- Records are scoped to the account through the token's user. A record in another account is a
  `404`, never a `403`, so IDs do not leak.
- Adding users to an account later is a new RFC, not a change to this one: records already belong
  to accounts, not to users.

## Alternatives considered

- **Ping CRM's model in full.** Rejected for v1: more surface, no new standards.
