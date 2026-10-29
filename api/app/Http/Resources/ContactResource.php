<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ContactResource extends JsonResource
{
    /**
     * One contact as the API sends it, for the documentation.
     */
    public const array EXAMPLE = [
        'id' => '01jbd1k9r2w3x4y5z6a7b8c9d0',
        'organization_id' => '01jbd0x4qsm2cf4c8m3x6y1v7z',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@rippin.example',
        'phone' => '+1 555 0101',
        'address' => '742 Evergreen Terrace',
        'city' => 'Springfield',
        'region' => 'Oregon',
        'country' => 'US',
        'postal_code' => '97403',
        'created_at' => '2026-10-29T09:00:00+00:00',
        'updated_at' => '2026-10-29T09:00:00+00:00',
        'deleted_at' => null,
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Contact $contact */
        $contact = $this->resource;

        return [
            'id' => $contact->id,
            'organization_id' => $contact->organization_id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'address' => $contact->address,
            'city' => $contact->city,
            'region' => $contact->region,
            'country' => $contact->country,
            'postal_code' => $contact->postal_code,
            'created_at' => $contact->created_at?->toAtomString(),
            'updated_at' => $contact->updated_at?->toAtomString(),
            'deleted_at' => $contact->deleted_at?->toAtomString(),
        ];
    }

    /**
     * The ETag goes with every response that carries one contact, writes included, as it does
     * for an organisation.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        /** @var Contact $contact */
        $contact = $this->resource;

        $entityTag = $contact->entityTag();

        if (null !== $entityTag) {
            $response->headers->set('ETag', $entityTag->toString());
        }
    }
}
