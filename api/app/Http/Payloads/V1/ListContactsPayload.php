<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

final readonly class ListContactsPayload
{
    /**
     * @param 'with'|'only'|null $trashed
     */
    public function __construct(
        public ?string $search,
        public ?string $trashed,
        public ?string $organizationId,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = self::validatedData($request);

        $search = isset($data['search']) && is_string($data['search']) ? mb_trim($data['search']) : '';
        $trashed = $data['trashed'] ?? null;
        $organizationId = $data['organization_id'] ?? null;

        return new self(
            search: '' === $search ? null : $search,
            trashed: 'with' === $trashed || 'only' === $trashed ? $trashed : null,
            organizationId: is_string($organizationId) ? mb_strtolower($organizationId) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function validatedData(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $request instanceof FormRequest ? $request->validated() : $request->all();

        return $data;
    }
}
