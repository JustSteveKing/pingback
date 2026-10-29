<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\Validator;
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
            // Deprecated (ADR-0007): one number, which replaces the list. Never with phones.
            'phone' => ['nullable', 'string', 'max:50'],
            'phones' => ['array', 'max:10'],
            'phones.*' => ['array:number,label'],
            'phones.*.number' => ['required', 'string', 'max:50'],
            'phones.*.label' => ['nullable', 'string', 'max:25'],
            'address' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:50'],
            'region' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'postal_code' => ['nullable', 'string', 'max:25'],
        ];
    }

    /**
     * The deprecated phone field, written as the list it stands for: one number replaces the
     * list, and null empties it. Sent beside phones, both are kept so validation refuses them.
     *
     * @param  array<string, mixed>  $document  the record as it would be saved
     * @param  array<string, mixed>  $sent  what the client sent
     * @return array<string, mixed>
     */
    public static function phoneAsPhones(array $document, array $sent): array
    {
        if ( ! array_key_exists('phone', $sent) || array_key_exists('phones', $sent)) {
            return $document;
        }

        $phone = $sent['phone'];
        unset($document['phone']);

        // A malformed phone stays, so validation reports it under its own name.
        if (null !== $phone && ! is_string($phone)) {
            return [...$document, 'phone' => $phone];
        }

        return [...$document, 'phones' => null === $phone ? [] : [['number' => $phone, 'label' => null]]];
    }

    /**
     * Refuses phone beside phones, whatever either holds. Laravel's prohibits rule lets an
     * empty phones through, and an empty list is a real request: clear every number.
     *
     * @param  array<string, mixed>  $sent  what the client sent
     */
    public static function notBothPhones(array $sent): Closure
    {
        return static function (Validator $validator) use ($sent): void {
            if (array_key_exists('phone', $sent) && array_key_exists('phones', $sent)) {
                $validator->errors()->add('phone', __('api.contacts.phone_with_phones'));
            }
        };
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
