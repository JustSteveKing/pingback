<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organizations;

use App\Http\Payloads\V1\CreateOrganizationPayload;
use App\Http\Requests\Organizations\CreateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
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

#[Group(name: 'Organizations')]
#[Endpoint(
    title: 'Create Organization',
    description: 'Add an organisation to the account. Send an Idempotency-Key, so a retry after a timeout does not create it twice. Returns the organisation with its ETag, and its URL in Location.',
)]
#[Authenticated]
#[ResponseField('data.email', 'string', 'The organisation\'s email address, or null.', nullable: true)]
#[ResponseField('data.phone', 'string', 'A phone number, or null.', nullable: true)]
#[ResponseField('data.address', 'string', 'The street address, or null.', nullable: true)]
#[ResponseField('data.city', 'string', 'The city, or null.', nullable: true)]
#[ResponseField('data.region', 'string', 'The state, province or county, or null.', nullable: true)]
#[ResponseField('data.country', 'string', 'A two-letter ISO 3166-1 code, or null.', nullable: true)]
#[ResponseField('data.postal_code', 'string', 'The postal code, or null.', nullable: true)]
#[ResponseField('data.deleted_at', 'string', 'When it was deleted, or null if it is not.', nullable: true)]
#[Header('Idempotency-Key', '"organization-retry-1"')]
#[BodyParam('name', type: 'string', description: 'Up to 100 characters.', required: true, example: 'Rippin, Kovacek and Bins')]
#[BodyParam('email', type: 'string', description: 'An email address, up to 50 characters, or null.', required: false, example: 'info@rippin.example')]
#[BodyParam('phone', type: 'string', description: 'Up to 50 characters, or null.', required: false, example: '+1 555 0100')]
#[BodyParam('address', type: 'string', description: 'Up to 150 characters, or null.', required: false, example: '742 Evergreen Terrace')]
#[BodyParam('city', type: 'string', description: 'Up to 50 characters, or null.', required: false, example: 'Springfield')]
#[BodyParam('region', type: 'string', description: 'A state, province or county, up to 50 characters, or null.', required: false, example: 'Oregon')]
#[BodyParam('country', type: 'string', description: 'A two-letter ISO 3166-1 code in capitals, or null.', required: false, example: 'US')]
#[BodyParam('postal_code', type: 'string', description: 'Up to 25 characters, or null.', required: false, example: '97403')]
#[Response(content: ['data' => OrganizationResource::EXAMPLE], status: 201, description: 'Organisation created.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'The name field is required.',
        'errors' => ['name' => ['The name field is required.']],
    ],
    status: 422,
    description: 'A field is missing, too long or malformed.',
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
final class CreateOrganizationController
{
    public function __invoke(CreateOrganizationRequest $request, #[CurrentUser] User $user, Webhooks $webhooks): JsonResponse
    {
        $payload = CreateOrganizationPayload::fromRequest($request);

        $organization = DB::transaction(static function () use ($user, $payload, $webhooks): Organization {
            $organization = $user->account()->firstOrFail()->organizations()->create($payload->attributes());
            $webhooks->dispatch($user, RecordChanged::organization('created', $organization));

            return $organization;
        });

        return OrganizationResource::make($organization)
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('v1.organizations.show', $organization));
    }
}
