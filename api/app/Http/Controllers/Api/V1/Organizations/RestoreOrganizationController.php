<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Organizations')]
#[Endpoint(
    title: 'Restore Organization',
    description: 'Bring back a deleted organisation, so it is listed again. Restoring one that is not deleted changes nothing, so a retry is safe without an Idempotency-Key. Returns the organisation with its new ETag.',
)]
#[UrlParam('organization', type: 'string', description: 'The organisation ID, a ULID.', required: true, example: '01jbd0x4qsm2cf4c8m3x6y1v7z')]
#[Authenticated]
#[ResponseField('data.email', 'string', 'The organisation\'s email address, or null.', nullable: true)]
#[ResponseField('data.phone', 'string', 'A phone number, or null.', nullable: true)]
#[ResponseField('data.address', 'string', 'The street address, or null.', nullable: true)]
#[ResponseField('data.city', 'string', 'The city, or null.', nullable: true)]
#[ResponseField('data.region', 'string', 'The state, province or county, or null.', nullable: true)]
#[ResponseField('data.country', 'string', 'A two-letter ISO 3166-1 code, or null.', nullable: true)]
#[ResponseField('data.postal_code', 'string', 'The postal code, or null.', nullable: true)]
#[ResponseField('data.deleted_at', 'string', 'When it was deleted, or null if it is not.', nullable: true)]
#[Response(content: ['data' => OrganizationResource::EXAMPLE], status: 200, description: 'The organisation, restored.')]
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
    description: 'Token is missing the organizations:write ability.',
)]
final class RestoreOrganizationController
{
    public function __invoke(Organization $organization): JsonResponse
    {
        if ($organization->trashed()) {
            $organization->restore();
        }

        return OrganizationResource::make($organization)->response();
    }
}
