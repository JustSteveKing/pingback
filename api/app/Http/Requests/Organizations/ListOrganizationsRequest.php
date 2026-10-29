<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizations;

use Illuminate\Foundation\Http\FormRequest;

final class ListOrganizationsRequest extends FormRequest
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
            'search' => ['description' => 'Only organisations whose name or email contains this text, ignoring case.', 'example' => 'acme'],
            'trashed' => ['description' => 'Deleted organisations are left out unless this is "with" (include them) or "only" (nothing else).', 'example' => 'with'],
        ];
    }
}
