<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;

final class CreateContactRequest extends FormRequest
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
        return ContactRules::rules($this);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ContactRules::messages();
    }
}
