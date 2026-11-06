---
id: ADR-0009
title: Every response body is the representation itself
status: accepted
created: 2026-11-06
decided: 2026-11-06
depends: [RFC-0001, ADR-0002, ADR-0004, ADR-0006]
updates: []
obsoletes: []
---

# ADR-0009: Every response body is the representation itself

## Context

Pingback's v1 answers in two shapes. A list is api-toolbox's `CursorPage`: the body is a bare JSON
array of the items, and the way to the next page is in the `Link` header. A single resource is
Laravel's `JsonResource`, which wraps it as `{"data": {...}}`, and five endpoints that return a
one-time value beside the resource (a token or a signing secret) add a `meta` object next to that
`data`. So a client that has just read a list of contacts as an array reads one contact as an
object holding another object, and has to know which kind of endpoint it called before it can find
the fields.

apiguide.dev's [response envelopes guide](https://apiguide.dev/guides/response-envelopes) treats an
envelope and a bare body as both defensible, and pagination in the body or in `Link` headers as
both workable, but says "The one non-negotiable is consistency across the whole API surface", with
one strategy applied to collections and individual resources alike. Pingback has two strategies,
which is the one thing the guide says not to have.

Pingback got the split from Kit. Kit has the same one (`CursorPage` lists, wrapped single
resources), and under [ADR-0002](0002-kit-and-sdk-toolbox-are-upstream.md) Kit is upstream,
which Pingback adds to and changes only where it has to.

## Decision

Every response body is the representation itself, with no envelope, and what describes it travels
in headers: `ETag` for the version ([ADR-0004](0004-updates-are-merge-patch-and-require-if-match.md)),
`Location` for where a new organisation or contact lives, and `Link` for pages, as `CursorPage`
already does. Decided by Steve on 2026-11-06. The API calls
`JsonResource::withoutWrapping()` once, in `AppServiceProvider`, rather than unwrapping each
resource in turn, so a resource added later is bare without anyone remembering to make it so.
Problem details, the async operation monitor and the export document were never wrapped and are
unchanged, and neither is the `data` attribute of a CloudEvents webhook, which belongs to that
format and not to this decision.

### The five endpoints that returned a value beside the resource

Each of these returned `{"data": ..., "meta": ...}`, and Laravel brings the `data` wrapper back
whenever `->additional()` is used, whatever `withoutWrapping()` says. Each now returns the
representation with the extra fields at its root, built from the resource's own `resolve()`:

- `POST /v1/auth/tokens`: the API key, with `token` and `token_type` beside its fields, where they
  were `meta.token` and `meta.token_type`. Nothing else about a token carries its plain-text value,
  so on this response it is part of the representation.
- `POST /v1/webhooks` and `POST /v1/webhooks/{webhook}/secret`: the subscription, with `secret`
  beside its fields, where it was `meta.secret`, for the same reason as a token's value.
- `POST /v1/auth/login` and `POST /v1/auth/register`: the user, with `token`, `token_type` and
  `token_expires_at` beside the user's fields, where they were `meta.token`, `meta.token_type` and
  `meta.expires_at`. The expiry is renamed because at the root of a user `expires_at` would read as
  something about the user.

A secret that is only ever returned once is documented on the endpoint that returns it, in the
endpoint's description, in its success response's description and in a `#[ResponseField]` for the
field, rather than through a separate envelope that says so by its shape. A `GET` of the same
resource never carries it.

## Alternatives

- **Wrap the lists instead, so everything is `{"data": ...}`.** Consistent too, but it fights
  api-toolbox's `CursorPage`, whose body is the list of items and whose navigation is in `Link`
  headers, so Pingback would have to change or wrap an upstream type to put nothing new in the body.
  The envelope would also hold nothing for a single resource, whose metadata (its `ETag`, and the
  `Location` of a new organisation or contact) is already in headers.
- **Keep `{data, meta}` for the five endpoints with a one-time value, and unwrap the rest.** That
  leaves five bodies in a different shape from the other twenty or so, which is the inconsistency
  this decision is there to remove, and login and register are among the first calls any client
  makes.
- **Leave the split as Kit has it.** That is the one choice the guide rules out.

## Consequences

- This is a breaking change to v1's representation, made without a v2. It is made now because the
  only consumer is this repository's own SDK, which is not on Packagist, has never been released,
  and moves in the same change. The contract suite of
  [ADR-0006](0006-the-sdk-is-tested-against-the-running-api.md) runs it against the API on every
  commit, so the two cannot drift apart unnoticed. The cheapest moment to break something is before
  its first consumer.
- A client reads every body the same way: a list is an array of representations and anything else
  is one representation, and the SDK maps the root of the body rather than a `data` key.
- Endpoints that return a resource with fields beside it cannot use `->additional()`, or Scribe's
  `additional:`, without the wrapper coming back. They build the body with `resolve()` and document
  it with an explicit `#[Response]`.
- Kit keeps its split. This decision is Pingback's and changes nothing upstream; whether Kit should
  make the same choice is a question for Kit.
