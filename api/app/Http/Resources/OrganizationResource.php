<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OrganizationResource extends JsonResource
{
    /**
     * One organisation as the API sends it, for the documentation.
     */
    public const array EXAMPLE = [
        'id' => '01jbd0x4qsm2cf4c8m3x6y1v7z',
        'name' => 'Rippin, Kovacek and Bins',
        'email' => 'info@rippin.example',
        'phone' => '+1 555 0100',
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
        /** @var Organization $organization */
        $organization = $this->resource;

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'email' => $organization->email,
            'phone' => $organization->phone,
            'address' => $organization->address,
            'city' => $organization->city,
            'region' => $organization->region,
            'country' => $organization->country,
            'postal_code' => $organization->postal_code,
            'created_at' => $organization->created_at?->toAtomString(),
            'updated_at' => $organization->updated_at?->toAtomString(),
            'deleted_at' => $organization->deleted_at?->toAtomString(),
        ];
    }

    /**
     * The ETag goes with every response that carries one organisation, writes included, so a
     * client can make its next change without fetching first. api-toolbox's validators middleware
     * adds it to reads only, since an unsafe method's response is not always the representation;
     * here it always is.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        /** @var Organization $organization */
        $organization = $this->resource;

        $entityTag = $organization->entityTag();

        if (null !== $entityTag) {
            $response->headers->set('ETag', $entityTag->toString());
        }
    }
}
