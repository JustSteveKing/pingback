<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use App\Models\Contact;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use JustSteveKing\Toolbox\MergePatch\MergePatch;

/**
 * A JSON Merge Patch (RFC 7396), applied to the current contact; the merged document is what gets
 * validated (ADR-0004). The organisation is checked only when the patch changes it, so a contact
 * whose organisation was deleted since can still have its other fields edited.
 */
final class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $contact = $this->contact();
        $merged = $this->validationData();

        $unchanged = $contact instanceof Contact
            && ($merged['organization_id'] ?? null) === $contact->organization_id;

        return ContactRules::rules($this, checkOrganization: ! $unchanged);
    }

    /**
     * @return list<Closure>
     */
    public function after(): array
    {
        /** @var array<string, mixed> $sent */
        $sent = $this->all();

        return [ContactRules::notBothPhones($sent)];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ContactRules::messages();
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        $contact = $this->contact();

        // Only Scribe reads the rules with no route, to document the body.
        if ( ! $contact instanceof Contact) {
            return [];
        }

        $merged = app(MergePatch::class)->applyTo($contact->only(Contact::FIELDS));

        /** @var array<string, mixed> $document */
        $document = is_array($merged) ? $merged : [];
        /** @var array<string, mixed> $sent */
        $sent = $this->all();

        return ContactRules::phoneAsPhones($document, $sent);
    }

    private function contact(): ?Contact
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact ? $contact : null;
    }
}
