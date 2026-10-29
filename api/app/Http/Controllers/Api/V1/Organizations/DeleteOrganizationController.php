<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Models\Organization;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Organizations')]
#[Endpoint(
    title: 'Delete Organization',
    description: 'Delete an organisation. It is kept, marked deleted, and can be restored; listings leave it out unless asked. If-Match is required, so nothing is deleted on the strength of a stale read. Returns 204 with no body, and deleting it again changes nothing.',
)]
#[UrlParam('organization', type: 'string', description: 'The organisation ID, a ULID.', required: true, example: '01jbd0x4qsm2cf4c8m3x6y1v7z')]
#[Authenticated]
#[Header('If-Match', '"a1b2c3d4e5f6"')]
#[ScribeResponse(content: null, status: 204, description: 'Organisation deleted.')]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'No organisation with this ID belongs to the authenticated account.',
)]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/precondition-failed',
        'title' => 'Precondition Failed',
        'status' => 412,
        'detail' => 'The resource has changed since you last fetched it. Fetch it again and retry with its current ETag.',
    ],
    status: 412,
    description: 'If-Match does not match the current ETag: someone else changed it first.',
)]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/precondition-required',
        'title' => 'Precondition Required',
        'status' => 428,
        'detail' => 'This request must be conditional. Fetch the resource, then retry with its ETag in If-Match.',
    ],
    status: 428,
    description: 'The request has no If-Match.',
)]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/unauthorized',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
    ],
    status: 401,
    description: 'Authentication failed.',
)]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'Forbidden.',
    ],
    status: 403,
    description: 'Token is missing the organizations:write ability.',
)]
final class DeleteOrganizationController
{
    public function __invoke(Organization $organization): Response
    {
        if ( ! $organization->trashed()) {
            $organization->delete();
        }

        return response()->noContent();
    }
}
