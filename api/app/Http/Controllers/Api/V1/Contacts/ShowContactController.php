<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Contacts')]
#[Endpoint(
    title: 'Show Contact',
    description: 'Fetch one contact by its ID, deleted or not. Use it before changing or deleting one: the response\'s ETag goes in If-Match. Returns the contact, or 304 with no body when If-None-Match still matches.',
)]
#[UrlParam('contact', type: 'string', description: 'The contact ID, a ULID.', required: true, example: '01jbd1k9r2w3x4y5z6a7b8c9d0')]
#[Authenticated]
#[ResponseField('data.organization_id', 'string', 'The organisation the contact is at, or null. It may since have been deleted.', nullable: true)]
#[ResponseField('data.email', 'string', 'The contact\'s email address, or null.', nullable: true)]
#[ResponseField('data.phone', 'string', 'A phone number, or null.', nullable: true)]
#[ResponseField('data.address', 'string', 'The street address, or null.', nullable: true)]
#[ResponseField('data.city', 'string', 'The city, or null.', nullable: true)]
#[ResponseField('data.region', 'string', 'The state, province or county, or null.', nullable: true)]
#[ResponseField('data.country', 'string', 'A two-letter ISO 3166-1 code, or null.', nullable: true)]
#[ResponseField('data.postal_code', 'string', 'The postal code, or null.', nullable: true)]
#[ResponseField('data.deleted_at', 'string', 'When it was deleted, or null if it is not.', nullable: true)]
#[Header('If-None-Match', '"a1b2c3d4e5f6"')]
#[Response(content: ['data' => ContactResource::EXAMPLE], status: 200, description: 'The contact, with its ETag.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'No contact with this ID belongs to the authenticated account.',
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
final class ShowContactController
{
    public function __invoke(Contact $contact): JsonResponse
    {
        return ContactResource::make($contact)->response();
    }
}
