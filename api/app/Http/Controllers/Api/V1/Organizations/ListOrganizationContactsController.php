<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Http\Controllers\Api\V1\Contacts\ListContactsController;
use App\Http\Payloads\V1\ListContactsPayload;
use App\Http\Requests\Organizations\ListOrganizationContactsRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\Organization;
use JustSteveKing\Toolbox\Pagination\CursorPage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Organizations')]
#[Endpoint(
    title: 'List Organization Contacts',
    description: 'List the contacts at one organisation, ordered by last name then first name, 25 to a page, leaving deleted ones out unless asked. Use it in place of List Contacts with organization_id, which is deprecated. Returns an array of contacts; follow the Link header\'s rel="next" for the next page.',
)]
#[UrlParam('organization', type: 'string', description: 'The organisation ID, a ULID. A deleted organisation still lists its contacts.', required: true, example: '01jbd0x4qsm2cf4c8m3x6y1v7z')]
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
    description: 'The trashed filter is not "with" or "only", or the search is longer than 100 characters.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'No organisation with this ID belongs to the authenticated account.',
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
final class ListOrganizationContactsController
{
    /**
     * @return CursorPage<int, Contact>
     */
    public function __invoke(ListOrganizationContactsRequest $request, Organization $organization): CursorPage
    {
        $payload = ListContactsPayload::fromRequest($request);

        return ListContactsController::page($organization->account, $payload->search, $payload->trashed, $organization->id);
    }
}
