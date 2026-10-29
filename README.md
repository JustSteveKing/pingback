# Pingback

A small CRM API and the PHP SDK that calls it, in one repository, built to show both ends of the
wire: an API on [Kit](https://github.com/JustSteveKing/kit) and
[api-toolbox](https://github.com/JustSteveKing/api-toolbox), and an SDK on
[sdk-toolbox](https://github.com/JustSteveKing/sdk-toolbox), written to the same HTTP standards and
tested against each other.

The domain comes from Inertia's [Ping CRM](https://github.com/inertiajs/pingcrm), taken API-first.

It is being built. The design is in [`architecture/`](architecture/README.md), starting with
[RFC-0001: Pingback v1](architecture/rfc/0001-pingback-v1.md).

## Running both ends

The API is a Kit application in `api/`, seeded with Ping CRM's demo account (Acme Corporation, owned
by John Doe):

```bash
cd api
composer run setup
php artisan migrate:fresh --seed
php artisan serve
```

The SDK is in `sdk/`. Its contract suite runs it against that API, so the two ends have to agree:

```bash
cd sdk
composer install
PINGBACK_BASE_URI=http://127.0.0.1:8000/v1 composer test:contract
```

With no API running, `composer test` replays the responses the contract suite recorded. To record them
again, add `SDK_FIXTURES=record` to the contract run. CI does both on every push: the SDK's own checks
with the fixtures, and the contract suite against a freshly booted API.
