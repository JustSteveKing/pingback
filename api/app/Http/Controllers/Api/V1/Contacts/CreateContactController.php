<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Payloads\V1\CreateContactPayload;
use App\Http\Requests\Contacts\CreateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\User;
use App\Webhooks\Events\RecordChanged;
use App\Webhooks\Webhooks;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;

#[Group(name: 'Contacts')]
#[Endpoint(
    title: 'Create Contact',
    description: 'Add a contact to the account, at one of its organisations or none. Send an Idempotency-Key, so a retry after a timeout does not create it twice. Returns the contact with its ETag, and its URL in Location.',
)]
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
#[Header('Idempotency-Key', '"contact-retry-1"')]
#[BodyParam('organization_id', type: 'string', description: 'One of the account\'s organisations that is not deleted, or null for none.', required: false, example: '01jbd0x4qsm2cf4c8m3x6y1v7z')]
#[BodyParam('first_name', type: 'string', description: 'Up to 25 characters.', required: true, example: 'Ada')]
#[BodyParam('last_name', type: 'string', description: 'Up to 25 characters.', required: true, example: 'Lovelace')]
#[BodyParam('email', type: 'string', description: 'An email address, up to 50 characters, or null.', required: false, example: 'ada@rippin.example')]
#[BodyParam('phone', type: 'string', description: 'Up to 50 characters, or null.', required: false, example: '+1 555 0101')]
#[BodyParam('address', type: 'string', description: 'Up to 150 characters, or null.', required: false, example: '742 Evergreen Terrace')]
#[BodyParam('city', type: 'string', description: 'Up to 50 characters, or null.', required: false, example: 'Springfield')]
#[BodyParam('region', type: 'string', description: 'A state, province or county, up to 50 characters, or null.', required: false, example: 'Oregon')]
#[BodyParam('country', type: 'string', description: 'A two-letter ISO 3166-1 code in capitals, or null.', required: false, example: 'US')]
#[BodyParam('postal_code', type: 'string', description: 'Up to 25 characters, or null.', required: false, example: '97403')]
#[Response(content: ['data' => ContactResource::EXAMPLE], status: 201, description: 'Contact created.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'The first name field is required.',
        'errors' => ['first_name' => ['The first name field is required.']],
    ],
    status: 422,
    description: 'A field is missing, too long or malformed, or the organisation is not one of the account\'s.',
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
final class CreateContactController
{
    public function __invoke(CreateContactRequest $request, #[CurrentUser] User $user, Webhooks $webhooks): JsonResponse
    {
        $payload = CreateContactPayload::fromRequest($request);

        $contact = DB::transaction(static function () use ($user, $payload, $webhooks): Contact {
            $contact = $user->account()->firstOrFail()->contacts()->create($payload->attributes());
            $webhooks->dispatch($user, RecordChanged::contact('created', $contact));

            return $contact;
        });

        return ContactResource::make($contact)
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('v1.contacts.show', $contact));
    }
}
