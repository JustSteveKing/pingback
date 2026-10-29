---
id: REF-0001
title: Ping CRM
verified: 2026-10-29
---

# REF-0001: Ping CRM

[Ping CRM](https://github.com/inertiajs/pingcrm) is the demo application for Inertia.js: "A demo
application to illustrate how Inertia.js works", MIT licensed, under the `inertiajs` organisation.
Pingback takes its domain, not its code. This is what it contained on the verified date, read from
its migrations and routes.

## Model

- **Account:** `name`. Everything else belongs to one.
- **User:** `account_id`, `first_name`, `last_name`, `email`, `password`, `owner` (boolean),
  `photo_path`, soft deletes.
- **Organization:** `account_id`, `name`, `email`, `phone`, `address`, `city`, `region`,
  `country`, `postal_code`, soft deletes.
- **Contact:** `account_id`, `organization_id` (nullable), `first_name`, `last_name`, `email`,
  `phone`, `address`, `city`, `region`, `country`, `postal_code`, soft deletes.

IDs are auto-increment integers.

## Behaviour

- Users, organisations and contacts each have index, create, edit, update, delete and restore.
  Restore is a `PUT` to `/{resource}/{id}/restore`.
- Index pages search and filter, including a "trashed" filter for soft-deleted records.
- A dashboard, and a reports page that is a placeholder.
- Seeders create a demo account and user (`johndoe@example.com`) and generated organisations and
  contacts.

## What Pingback changes

- API-first: JSON resources, no pages.
- IDs are ULIDs, Kit's convention, not integers.
- Restore becomes `POST /{resource}/{id}/restore`, since it is an action rather than a
  replacement.
- The reports placeholder becomes a real contact export.
- Users and multi-user accounts are out of v1 ([RFC-0001](../rfc/0001-pingback-v1.md)).
