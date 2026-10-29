<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizations;

use Illuminate\Foundation\Http\FormRequest;

final class CreateOrganizationRequest extends FormRequest
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
        return OrganizationRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return OrganizationRules::messages();
    }
}
