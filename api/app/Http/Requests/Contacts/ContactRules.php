<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What a contact must look like, shared by create and update. A contact's organisation must be
 * one of the account's own, and not deleted: another account's is refused exactly like one that
 * does not exist (ADR-0003).
 */
final class ContactRules
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(Request $request, bool $checkOrganization = true): array
    {
        // Scribe reads these rules with no user, to document the body.
        $user = $request->user();
        $accountId = $user instanceof User ? $user->account?->id : null;

        $organization = ['nullable', 'string'];

        if ($checkOrganization) {
            $organization[] = Rule::exists('organizations', 'id')
                ->where('account_id', $accountId)
                ->whereNull('deleted_at');
        }

        return [
            'organization_id' => $organization,
            'first_name' => ['required', 'string', 'max:25'],
            'last_name' => ['required', 'string', 'max:25'],
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
            'organization_id.exists' => __('api.contacts.organization_unknown'),
        ];
    }
}
