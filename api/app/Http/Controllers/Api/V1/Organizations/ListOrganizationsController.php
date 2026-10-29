<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Http\Payloads\V1\ListOrganizationsPayload;
use App\Http\Requests\Organizations\ListOrganizationsRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use JustSteveKing\Toolbox\Pagination\CursorPage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;

#[Group(name: 'Organizations')]
#[Endpoint(
    title: 'List Organizations',
    description: 'List the account\'s organisations, ordered by name, 25 to a page, leaving deleted ones out unless asked. Use it to browse or search organisations, or to find one\'s ID. Returns an array of organisations; follow the Link header\'s rel="next" for the next page, which keeps the same search and filter.',
)]
#[Authenticated]
#[ResponseField('[].email', 'string', 'The organisation\'s email address, or null.', nullable: true)]
#[ResponseField('[].phone', 'string', 'A phone number, or null.', nullable: true)]
#[ResponseField('[].address', 'string', 'The street address, or null.', nullable: true)]
#[ResponseField('[].city', 'string', 'The city, or null.', nullable: true)]
#[ResponseField('[].region', 'string', 'The state, province or county, or null.', nullable: true)]
#[ResponseField('[].country', 'string', 'A two-letter ISO 3166-1 code, or null.', nullable: true)]
#[ResponseField('[].postal_code', 'string', 'The postal code, or null.', nullable: true)]
#[ResponseField('[].deleted_at', 'string', 'When it was deleted, or null if it is not.', nullable: true)]
#[QueryParam('cursor', type: 'string', description: 'Opaque cursor from a Link header. Omit it for the first page.', required: false, example: null)]
#[Response(content: [OrganizationResource::EXAMPLE], status: 200, description: 'One page of organisations.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'The selected trashed is invalid.',
        'errors' => ['trashed' => ['The selected trashed is invalid.']],
    ],
    status: 422,
    description: 'The trashed filter is not "with" or "only", or the search is longer than 100 characters.',
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
    description: 'Token is missing the organizations:read ability.',
)]
final class ListOrganizationsController
{
    /**
     * The escape character for LIKE patterns. A backslash would need escaping differently in each
     * database's string literals; this does not.
     */
    private const string ESCAPE = '!';

    /**
     * @return CursorPage<int, Organization>
     */
    public function __invoke(ListOrganizationsRequest $request, #[CurrentUser] User $user): CursorPage
    {
        $payload = ListOrganizationsPayload::fromRequest($request);

        $organizations = Organization::query()
            ->whereBelongsTo($user->account()->firstOrFail())
            ->when('with' === $payload->trashed, static fn(Builder $query): Builder => $query->withTrashed())
            ->when('only' === $payload->trashed, static fn(Builder $query): Builder => $query->onlyTrashed())
            ->when(null !== $payload->search, static function (Builder $query) use ($payload): void {
                $pattern = '%' . self::escape(mb_strtolower((string) $payload->search)) . '%';

                $query->where(static function (Builder $query) use ($pattern): void {
                    $query->whereRaw("lower(name) like ? escape '" . self::ESCAPE . "'", [$pattern])
                        ->orWhereRaw("lower(email) like ? escape '" . self::ESCAPE . "'", [$pattern]);
                });
            })
            // The ID breaks ties between equal names, so the cursor never skips or repeats a row.
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate(25)
            ->withQueryString();

        return CursorPage::of($organizations, OrganizationResource::class);
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
