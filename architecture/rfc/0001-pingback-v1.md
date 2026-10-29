---
id: RFC-0001
title: Pingback v1
status: accepted
created: 2026-10-29
decided: 2026-10-29
depends: []
updates: []
obsoletes: []
---

# RFC-0001: Pingback v1

## Abstract

Pingback is a small CRM API and the PHP SDK that calls it, in one repository. The API is built on
Kit and api-toolbox, the SDK on sdk-toolbox. Its domain is Inertia's Ping CRM
([REF-0001](../ref/0001-ping-crm.md)), taken API-first: organisations and contacts in an account,
an asynchronous contact export, and webhooks. Its purpose is to show both ends of the wire at once:
every HTTP standard the two toolboxes share has a job here, and the SDK's tests run against the
real API to prove the two ends agree.

## Motivation

[Flagpole](https://github.com/JustSteveKing/flagpole) shows Kit and api-toolbox in a real
application, and [todoist-sdk](https://github.com/JustSteveKing/todoist-sdk) shows sdk-toolbox
against a real API. Neither shows the claim the two toolboxes are built on: that an API and a
client written to the same standards need no private agreement between them. A `Retry-After` the
server sends is one the client waits for; a problem `type` the server documents is one the client
turns into an exception; a `412` the server returns is one the client can explain.

Showing that needs both ends in one place, built against each other, with a test that fails when
they stop agreeing. A CRM is a good domain for it because everyone knows what it does, so nothing
has to be explained before the standards can be, and because every standard has a real reason to
be there rather than being decoration.

## Scope

### In v1

- **Organisations and contacts:** list with search and filters, read, create, update, delete,
  restore. Contacts may belong to an organisation.
- **Contact export:** the Ping CRM reports page was a stub; here it is a real asynchronous export
  of the account's contacts to CSV ([ADR-0005](../adr/0005-contact-export-is-asynchronous.md)).
- **Webhooks:** Kit's signed CloudEvents for changes to organisations and contacts, and for a
  finished export.
- **Authentication:** Kit's registration, session tokens and API keys with abilities. Registering
  creates an account its user owns ([ADR-0003](../adr/0003-an-account-is-its-owner.md)).
- **The SDK:** a connector, a resource per area, typed models, every endpoint above, webhook
  verification, and tests that run against the API ([ADR-0006](../adr/0006-the-sdk-is-tested-against-the-running-api.md)).

### Not in v1

- Users and account management through the API: more than one user per account, invitations,
  roles beyond the owner.
- Photos and file uploads.
- A UI. Pingback is an API and an SDK.
- Releasing the SDK on Packagist. It is a demonstration, like todoist-sdk.

## The standards, end to end

Every standard api-toolbox and sdk-toolbox both implement, and the job it has here.

| Need | Standard | The API | The SDK |
|---|---|---|---|
| Listing contacts with search and filters | RFC 8288 `Link` | cursor pages, `Link: rel="next"` | `LinkPagination`; a loop gets every page |
| Two people editing one contact | RFC 9110 §13, RFC 6585 (428) | an ETag on every resource; `412` on a stale `If-Match`, `428` without one | `If-Match` from the last read; `PreconditionFailedException` |
| Partial updates | RFC 7396 merge patch | `PATCH` with `application/merge-patch+json` | `MergePatch::diff()` sends what changed |
| A retried create must not duplicate | Idempotency-Key (draft) | the original response replayed | one key kept across retries |
| Errors | RFC 9457 problem details | typed problems from apiguide.dev | `ProblemTypes` maps them to exceptions |
| Exporting contacts | RFC 7240 `Prefer`, RFC 9110 §15.3.3 | `respond-async`, `202`, a monitor, `303` to the file | `await()` follows it |
| Busy API keys | RateLimit headers (draft), RFC 6585 (429) | headers on every response | the throttle waits rather than collecting `429`s |
| Webhooks a receiver can trust | RFC 9421 signatures, RFC 9530 `Content-Digest` | Kit's signed CloudEvents | `SignedWebhook` verifies them |
| Changing the contract later | RFC 9745 `Deprecation`, RFC 8594 `Sunset` | see the open question below | `Deprecations::strict()` in the SDK's tests |
| Content negotiation | RFC 9110 §12 | `application/json` and `application/problem+json` | the right `Accept` on every request |

Kit adds what isn't on both sides: `/.well-known/api-catalog` (RFC 9727), request IDs and trace
context, and its OpenAPI document checked against the running API on every build.

## Layout

One repository ([ADR-0001](../adr/0001-one-repository-for-the-api-and-the-sdk.md)):

- `api/`: the Laravel application, created from Kit, which is upstream
  ([ADR-0002](../adr/0002-kit-and-sdk-toolbox-are-upstream.md)).
- `sdk/`: the PHP SDK, a Composer package requiring `juststeveking/sdk-toolbox`.
- `architecture/`: this record.

## Phases

Each phase ends with both ends working, so the contract test always has something to check.

1. **Skeleton:** `api/` from Kit, `sdk/` from nothing, and CI that boots the API and runs the
   SDK's tests against it. One endpoint end to end: read the account.
2. **Organisations:** the full resource on both ends, with every standard in the table that
   applies to it.
3. **Contacts:** the same, plus search, filters and the relationship to organisations.
4. **Export and webhooks:** the asynchronous export, and webhook events with verification in the
   SDK.
5. **Docs:** a README that walks one change through both ends, and an article.

## Open questions

- **Deprecation.** Shipping v1 with something already deprecated would be decoration, which is
  the lesson this project exists to avoid; [Flagpole](https://github.com/JustSteveKing/flagpole)
  left deprecation out of v1 for the same reason. The honest version is a real change after v1
  (for example, a contact's single `phone` becoming a list), made with `Deprecation` and `Sunset`
  on the old field and `Deprecations::strict()` turning the SDK's tests red until it moves. That
  would also be a worked example for the *Evolving an API* talk. Proposed: out of v1, planned as
  the first change after it.
- **Search.** Ping CRM searches name, email and organisation with `LIKE`. Whether that is enough
  here, or Pingback needs something better, depends on the seeded data size.
