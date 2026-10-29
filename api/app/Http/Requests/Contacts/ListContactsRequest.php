<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;

final class ListContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Body or query input only: path parameters are validated by the route's constraints.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:100'],
            'trashed' => ['sometimes', 'string', 'in:with,only'],
            'organization_id' => ['sometimes', 'string', 'ulid'],
        ];
    }

    /**
     * Read by Scribe, so this GET's rules are documented as query parameters, not a body.
     *
     * @return array<string, array{description: string, example: mixed}>
     */
    public function queryParameters(): array
    {
        return [
            'search' => ['description' => 'Only contacts whose first name, last name, email or organisation name contains this text, ignoring case.', 'example' => 'ada'],
            'trashed' => ['description' => 'Deleted contacts are left out unless this is "with" (include them) or "only" (nothing else).', 'example' => 'with'],
            'organization_id' => ['description' => 'Deprecated, with a sunset of 2027-01-31: use List Organization Contacts. Only the contacts at this organisation.', 'example' => '01jbd0x4qsm2cf4c8m3x6y1v7z'],
        ];
    }
}
