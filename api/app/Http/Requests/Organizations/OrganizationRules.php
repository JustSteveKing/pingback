<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizations;

/**
 * What an organisation must look like, shared by create and update. Update validates the merged
 * document rather than the patch, so both check the same thing: the record as it would be saved.
 */
final class OrganizationRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:50'],
            'region' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'postal_code' => ['nullable', 'string', 'max:25'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'country.regex' => __('api.organizations.country_format'),
        ];
    }
}
