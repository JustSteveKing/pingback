<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Payloads\V1\ListContactsPayload;
use App\Http\Requests\Contacts\ListContactsRequest;
use App\Http\Resources\ContactResource;
use App\Models\Account;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use JustSteveKing\Toolbox\Deprecation\Attributes\Deprecation;
use JustSteveKing\Toolbox\Deprecation\Attributes\Sunset;
use JustSteveKing\Toolbox\Pagination\CursorPage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;

// The organisation filter is a resource of its own, its query part of its URI, and it alone is
// deprecated: /contacts without it is not (ADR-0008).
#[Deprecation(at: '2026-10-29T00:00:00Z', link: 'https://github.com/JustSteveKing/pingback/blob/main/architecture/adr/0008-the-organisation-filter-becomes-a-resource-and-is-deprecated.md', query: 'organization_id')]
#[Sunset(at: '2027-01-31T23:59:59Z', query: 'organization_id')]
#[Group(name: 'Contacts')]
#[Endpoint(
    title: 'List Contacts',
    description: 'List the account\'s contacts, ordered by last name then first name, 25 to a page, leaving deleted ones out unless asked. Use it to browse or search contacts; for the people at one organisation, use List Organization Contacts, since filtering this list by organization_id is deprecated. Returns an array of contacts; follow the Link header\'s rel="next" for the next page, which keeps the same search and filter.',
)]
#[Authenticated]
#[ResponseField('[].organization_id', 'string', 'The organisation the contact is at, or null. It may since have been deleted.', nullable: true)]
#[ResponseField('[].email', 'string', 'The contact\'s email address, or null.', nullable: true)]
#[ResponseField('[].phone', 'string', 'Deprecated: the first of phones, or null. Read phones instead.', nullable: true)]
#[ResponseField('[].phones', 'array', 'The contact\'s phone numbers, each with an optional label.')]
#[ResponseField('[].address', 'string', 'The street address, or null.', nullable: true)]
#[ResponseField('[].city', 'string', 'The city, or null.', nullable: true)]
#[ResponseField('[].region', 'string', 'The state, province or county, or null.', nullable: true)]
#[ResponseField('[].country', 'string', 'A two-letter ISO 3166-1 code, or null.', nullable: true)]
#[ResponseField('[].postal_code', 'string', 'The postal code, or null.', nullable: true)]
#[ResponseField('[].deleted_at', 'string', 'When it was deleted, or null if it is not.', nullable: true)]
#[QueryParam('cursor', type: 'string', description: 'Opaque cursor from a Link header. Omit it for the first page.', required: false, example: null)]
#[Response(content: [ContactResource::EXAMPLE], status: 200, description: 'One page of contacts.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'The selected trashed is invalid.',
        'errors' => ['trashed' => ['The selected trashed is invalid.']],
    ],
    status: 422,
    description: 'The trashed filter is not "with" or "only", the organisation is not a ULID, or the search is longer than 100 characters.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/unauthorized',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
    ],
    status: 401,
    description: 'Authentication failed.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'Forbidden.',
    ],
    status: 403,
    description: 'Token is missing the contacts:read ability.',
)]
final class ListContactsController
{
    /**
     * The escape character for LIKE patterns. A backslash would need escaping differently in each
     * database's string literals; this does not.
     */
    private const string ESCAPE = '!';

    /**
     * @return CursorPage<int, Contact>
     */
    public function __invoke(ListContactsRequest $request, #[CurrentUser] User $user): CursorPage
    {
        $payload = ListContactsPayload::fromRequest($request);

        return self::page($user->account()->firstOrFail(), $payload->search, $payload->trashed, $payload->organizationId);
    }

    /**
     * One page of an account's contacts, shared with the organisation's own list (ADR-0008).
     *
     * @param  'with'|'only'|null  $trashed
     * @return CursorPage<int, Contact>
     */
    public static function page(Account $account, ?string $search, ?string $trashed, ?string $organizationId): CursorPage
    {
        $contacts = Contact::query()
            ->whereBelongsTo($account)
            ->when('with' === $trashed, static fn(Builder $query): Builder => $query->withTrashed())
            ->when('only' === $trashed, static fn(Builder $query): Builder => $query->onlyTrashed())
            ->when(null !== $organizationId, static fn(Builder $query): Builder => $query->where('organization_id', $organizationId))
            ->when(null !== $search, static function (Builder $query) use ($search): void {
                $pattern = '%' . self::escape(mb_strtolower((string) $search)) . '%';
                $like = "lower(%s) like ? escape '" . self::ESCAPE . "'";

                $query->where(static function (Builder $query) use ($pattern, $like): void {
                    $query->whereRaw(sprintf($like, 'first_name'), [$pattern])
                        ->orWhereRaw(sprintf($like, 'last_name'), [$pattern])
                        ->orWhereRaw(sprintf($like, 'email'), [$pattern])
                        ->orWhereHas('organization', static function (Builder $query) use ($pattern, $like): void {
                            $query->whereRaw(sprintf($like, 'name'), [$pattern]);
                        });
                });
            })
            // The ID breaks ties between equal names, so the cursor never skips or repeats a row.
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->with('organization')
            ->cursorPaginate(25)
            ->withQueryString();

        return CursorPage::of($contacts, ContactResource::class);
    }

    private static function escape(string $search): string
    {
        return str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE . self::ESCAPE, self::ESCAPE . '%', self::ESCAPE . '_'],
            $search,
        );
    }
}
