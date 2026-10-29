# Pingback

A small CRM API and the PHP SDK that calls it, in one repository, built to show both ends of the
wire: an API on [Kit](https://github.com/JustSteveKing/kit) and
[api-toolbox](https://github.com/JustSteveKing/api-toolbox), and an SDK on
[sdk-toolbox](https://github.com/JustSteveKing/sdk-toolbox), written to the same HTTP standards and
tested against each other.

The domain comes from Inertia's [Ping CRM](https://github.com/inertiajs/pingcrm), taken API-first:
organisations and contacts in an account, an asynchronous export of the contacts, and signed
webhooks for every change. The design is in [`architecture/`](architecture/README.md), starting
with [RFC-0001: Pingback v1](architecture/rfc/0001-pingback-v1.md).

The SDK is a demonstration, like [todoist-sdk](https://github.com/JustSteveKing/todoist-sdk). It is
not on Packagist.

## The point

An API and a client written to the same standards shouldn't need a private agreement between them.
A `412` the server returns is one the client can explain; a `Retry-After` the server sends is one
the client waits for; a webhook the server signs is one the client can verify. Pingback puts both
ends in one repository with a test suite that fails when they stop agreeing, and it has already
earned its keep: building it found two places where api-toolbox and sdk-toolbox did not agree, and
both were fixed upstream (see [What it found](#what-it-found)).

| Need | Standard | The API | The SDK |
|---|---|---|---|
| Paging through records | RFC 8288 `Link` | cursor pages, `rel="next"` | `LinkPagination` |
| Two people editing one record | RFC 9110 §13, RFC 6585 | strong ETags; `412` stale, `428` missing | `If-Match` from the last read |
| Partial updates | RFC 7396 merge patch | `PATCH` with `application/merge-patch+json` | `MergePatch::diff()` |
| A retried create | Idempotency-Key (draft) | the first response replayed | one key kept across retries |
| Errors | RFC 9457 | typed problems from apiguide.dev | an exception per status, carrying the problem |
| A slow export | RFC 7240 `Prefer`, RFC 9110 §15.3.3 | `202`, a monitor, `303` to the file | `await()` |
| Webhooks | RFC 9421, RFC 9530 | Kit's signed CloudEvents | `WebhookReceiver` |

## One change, both ends

Here is one ordinary thing a CRM does, changing an organisation's city, followed through both
ends. The HTTP below is not made up: it is what the contract suite recorded against the running API.

### The SDK reads, and keeps the ETag

```php
$pingback = new Pingback($token, 'http://127.0.0.1:8000/v1');

$organization = $pingback->organizations()->get($id);
```

Every response that carries one organisation sends its ETag, and the SDK keeps it on the
`Organization` it returns, so the version that was read travels with the data. On the API side the
ETag comes from a revision column that every write increments
([`HasRevision`](api/app/Models/Concerns/HasRevision.php)), because one built from `updated_at` is
only as fine as the database's timestamps.

### The change is a merge patch of what differs

```php
$organization = $pingback->organizations()->update($organization, $organization->with(city: 'Leeds'));
```

`with()` makes a changed copy. [`UpdateOrganization`](sdk/src/Requests/Organizations/UpdateOrganization.php)
sends `MergePatch::diff()` of the two, so only the city travels, under the ETag it was read with:

```http
PATCH /v1/organizations/01m63sykrncece9mg8ym9gzqqd
Content-Type: application/merge-patch+json
If-Match: "383b9f194b6075f34cfd12c007aea9c6"

{"city":"Leeds"}
```

Giving a field `null`, as in `with(email: null)`, clears it: in a merge patch, `null` means remove.

### The API checks the version, then the document

The route carries the whole contract in its middleware
([`routes/api/v1.php`](api/routes/api/v1.php)):

```php
Route::patch('/organizations/{organization}', UpdateOrganizationController::class)
    ->whereUlid('organization')
    ->middleware(['abilities:organizations:write', 'consumes:application/merge-patch+json', 'merge-patch', 'conditional', 'preconditions.required'])
    ->name('v1.organizations.update');
```

`consumes` refuses any other body with `415`. `preconditions.required` refuses a request with no
`If-Match` with `428`, and `conditional` compares the one it has with the current ETag. The
[form request](api/app/Http/Requests/Organizations/UpdateOrganizationRequest.php) applies the
patch to the current record and validates the merged document, not the patch, so clearing the
name is a `422` and clearing the email is fine. The record is bound within the token's account, so
another account's organisation is a `404`, exactly like one that does not exist.

```http
HTTP/1.1 200 OK
Content-Type: application/json
ETag: "e160ea4715259837b9a5747438bae987"
```

The new ETag comes back with the change, so the next edit needs no fresh read.

### Someone else got there first

Send the same change again with the original ETag, as a second person working from the same read
would, and the API refuses to overwrite:

```http
HTTP/1.1 412 Precondition Failed
Content-Type: application/problem+json
ETag: "e160ea4715259837b9a5747438bae987"

{"type":"https://apiguide.dev/errors/precondition-failed","title":"Precondition Failed","status":412,"detail":"The resource has changed since you last fetched it. Fetch it again and retry with its current ETag."}
```

and the SDK turns that into an exception carrying the problem:

```php
try {
    $pingback->organizations()->update($organization, $organization->with(city: 'York'));
} catch (PreconditionFailedException $e) {
    $current = $pingback->organizations()->get($organization->id);   // look again, then decide
}
```

The city is still Leeds. Nobody's change was silently lost.

### And everyone listening hears about it

The update dispatches an `organization.updated` webhook in the same transaction as the write, with
the record as it now is. Kit signs each delivery with the subscription's secret (RFC 9421, with an
RFC 9530 `Content-Digest` of the body), and the SDK checks both before it reads anything:

```php
$receiver = new WebhookReceiver($subscriptionId, $secret);
$event = $receiver->receive($request);   // the PSR-7 request, as it arrived

if ($event->type === 'organization.updated') {
    $organization = $event->organization();
}
```

### The test that holds it together

Each step above is a test in the SDK's contract suite, run against the real API on every push.
[`OrganizationsTest`](sdk/tests/Contract/OrganizationsTest.php) checks the stale update throws
`PreconditionFailedException` with the API's problem type and that the city is unchanged;
[`WebhooksTest`](sdk/tests/Contract/WebhooksTest.php) receives a real delivery and verifies it. If
the API changed its ETags, its problem types or its signatures without the SDK following, the
build would fail on the same commit.

## Running both ends

The API is a Kit application in `api/`, seeded with Ping CRM's demo account (Acme Corporation, owned
by John Doe, with a hundred organisations and a hundred contacts):

```bash
cd api
composer run setup
php artisan migrate:fresh --seed
API_RATE_LIMIT=600 php artisan serve &
API_RATE_LIMIT=600 php artisan queue:work &
```

Exports and webhook deliveries are queued, hence the worker. The SDK is in `sdk/`, and its contract
suite runs it against that API:

```bash
cd sdk
composer install
PINGBACK_BASE_URI=http://127.0.0.1:8000/v1 composer test:contract
```

The suite signs in as the demo user for every request, more than the API's default of 60 a minute
for one user, hence `API_RATE_LIMIT`. It receives real webhook deliveries on `127.0.0.1:8001`,
which `.env.example` allows by permitting plain-HTTP, private webhook targets; never do that in
production.

With no API running, `composer test` replays the responses the contract suite recorded. To record
them again, add `SDK_FIXTURES=record` to the contract run. CI does both on every push: the SDK's own
checks with the fixtures, and the contract suite against a freshly booted API.

## Using the SDK

```php
use JustSteveKing\Pingback\Inputs\NewContact;
use JustSteveKing\Pingback\Inputs\NewOrganization;
use JustSteveKing\Pingback\Pingback;

$pingback = new Pingback(getenv('PINGBACK_TOKEN'), 'https://pingback.example.com/v1');

// Every page, following the Link header as you go.
foreach ($pingback->organizations()->all(search: 'acme') as $organization) {
    echo $organization->name, PHP_EOL;
}

// Idempotent: a retry after a timeout gets the first response back, not a second organisation.
$acme = $pingback->organizations()->create(new NewOrganization('Acme', country: 'GB'));
$ada = $pingback->contacts()->create(new NewContact('Ada', 'Lovelace', organizationId: $acme->id));

// The contacts at one organisation.
$people = $pingback->contacts()->all(at: $acme)->all();

// Starts the export, waits on its monitor as Retry-After says, and reads the CSV.
foreach ($pingback->exports()->create()->rows() as $row) {
    echo $row['first_name'], ' ', $row['last_name'], ' at ', $row['organization'], PHP_EOL;
}
```

The token is a session token or an API key from the API, with the abilities its calls need
(`organizations:read`, `contacts:write`, `exports:create` and so on). Credentials only go over plain
HTTP to a loopback address.

## What it found

Building both ends against each other found disagreements that neither end's own tests could:

- **sdk-toolbox's `await()` and api-toolbox's monitor.** The monitor answers `200` with
  `Retry-After` while an operation runs; `await()` only kept polling on `202`, so it would have
  handed the status document to the SDK as the export. Fixed in
  [sdk-toolbox 0.0.4](https://github.com/JustSteveKing/sdk-toolbox/releases/tag/v0.0.4).
- **Kit's gitleaks allowlist** was anchored at the repository root, so with Kit in `api/` the
  security gate failed on generated files.
- **A queued job only knows `APP_URL`.** The export's `303` pointed at a host the client had never
  used until the URL was built in the request instead.
- **An HTTP client that follows redirects itself** hides the `303` from `await()`, and drops the
  token when the redirect changes host. The contract suite uses one that doesn't; the SDK copes
  either way.

The record of what was decided and why is in [`architecture/`](architecture/README.md).
