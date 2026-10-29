<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Payloads\V1\UpdateContactPayload;
use App\Http\Requests\Contacts\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Contacts')]
#[Endpoint(
    title: 'Update Contact',
    description: 'Change a contact with a JSON Merge Patch (application/merge-patch+json): send only the fields that change, and null to clear one. If-Match is required, with the ETag from the last read, so a change someone else made first is never overwritten. Returns the contact after the change, with its new ETag.',
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
#[Header('Content-Type', 'application/merge-patch+json')]
#[Header('If-Match', '"a1b2c3d4e5f6"')]
#[BodyParam('organization_id', type: 'string', description: 'One of the account\'s organisations that is not deleted, or null for none.', required: false, example: '01jbd0x4qsm2cf4c8m3x6y1v7z')]
#[BodyParam('first_name', type: 'string', description: 'Up to 25 characters.', required: false, example: 'Ada')]
#[BodyParam('last_name', type: 'string', description: 'Up to 25 characters.', required: false, example: 'Lovelace')]
#[BodyParam('email', type: 'string', description: 'An email address, up to 50 characters, or null.', required: false, example: 'ada@rippin.example')]
#[BodyParam('phone', type: 'string', description: 'Up to 50 characters, or null.', required: false, example: '+1 555 0101')]
#[BodyParam('address', type: 'string', description: 'Up to 150 characters, or null.', required: false, example: '742 Evergreen Terrace')]
#[BodyParam('city', type: 'string', description: 'Up to 50 characters, or null.', required: false, example: 'Springfield')]
#[BodyParam('region', type: 'string', description: 'A state, province or county, up to 50 characters, or null.', required: false, example: 'Oregon')]
#[BodyParam('country', type: 'string', description: 'A two-letter ISO 3166-1 code in capitals, or null.', required: false, example: 'US')]
#[BodyParam('postal_code', type: 'string', description: 'Up to 25 characters, or null.', required: false, example: '97403')]
#[Response(content: ['data' => ContactResource::EXAMPLE], status: 200, description: 'The contact after the change.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'The organisation is not one of this account\'s, or it is deleted.',
        'errors' => ['organization_id' => ['The organisation is not one of this account\'s, or it is deleted.']],
    ],
    status: 422,
    description: 'A field is too long or malformed, a name was cleared, or the organisation is not one of the account\'s.',
)]
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
        'type' => 'https://apiguide.dev/errors/precondition-failed',
        'title' => 'Precondition Failed',
        'status' => 412,
        'detail' => 'The resource has changed since you last fetched it. Fetch it again and retry with its current ETag.',
    ],
    status: 412,
    description: 'If-Match does not match the current ETag: someone else changed it first.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/precondition-required',
        'title' => 'Precondition Required',
        'status' => 428,
        'detail' => 'This request must be conditional. Fetch the resource, then retry with its ETag in If-Match.',
    ],
    status: 428,
    description: 'The request has no If-Match.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/unsupported-media-type',
        'title' => 'Unsupported Media Type',
        'status' => 415,
        'detail' => 'The Content-Type \'application/json\' is not supported. Supported content types: application/merge-patch+json.',
    ],
    status: 415,
    description: 'The body is not application/merge-patch+json.',
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
    description: 'Token is missing the contacts:write ability.',
)]
final class UpdateContactController
{
    public function __invoke(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        $payload = UpdateContactPayload::fromRequest($request);

        $contact->update($payload->attributes());

        return ContactResource::make($contact)->response();
    }
}
