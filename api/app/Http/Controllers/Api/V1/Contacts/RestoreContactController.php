<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Contacts;

use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\User;
use App\Webhooks\Events\RecordChanged;
use App\Webhooks\Webhooks;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseField;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Contacts')]
#[Endpoint(
    title: 'Restore Contact',
    description: 'Bring back a deleted contact, so it is listed again. Restoring one that is not deleted changes nothing, so a retry is safe without an Idempotency-Key. Returns the contact with its new ETag.',
)]
#[UrlParam('contact', type: 'string', description: 'The contact ID, a ULID.', required: true, example: '01jbd1k9r2w3x4y5z6a7b8c9d0')]
#[Authenticated]
#[ResponseField('data.organization_id', 'string', 'The organisation the contact is at, or null. It may since have been deleted.', nullable: true)]
#[ResponseField('data.email', 'string', 'The contact\'s email address, or null.', nullable: true)]
#[ResponseField('data.phone', 'string', 'Deprecated: the first of phones, or null. Read phones instead.', nullable: true)]
#[ResponseField('data.phones', 'array', 'The contact\'s phone numbers, each with an optional label.')]
#[ResponseField('data.address', 'string', 'The street address, or null.', nullable: true)]
#[ResponseField('data.city', 'string', 'The city, or null.', nullable: true)]
#[ResponseField('data.region', 'string', 'The state, province or county, or null.', nullable: true)]
#[ResponseField('data.country', 'string', 'A two-letter ISO 3166-1 code, or null.', nullable: true)]
#[ResponseField('data.postal_code', 'string', 'The postal code, or null.', nullable: true)]
#[ResponseField('data.deleted_at', 'string', 'When it was deleted, or null if it is not.', nullable: true)]
#[Response(content: ['data' => ContactResource::EXAMPLE], status: 200, description: 'The contact, restored.')]
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
    description: 'Token is missing the contacts:write ability.',
)]
final class RestoreContactController
{
    public function __invoke(Contact $contact, #[CurrentUser] User $user, Webhooks $webhooks): JsonResponse
    {
        if ($contact->trashed()) {
            DB::transaction(static function () use ($contact, $user, $webhooks): void {
                $contact->restore();
                $webhooks->dispatch($user, RecordChanged::contact('restored', $contact));
            });
        }

        return ContactResource::make($contact)->response();
    }
}
