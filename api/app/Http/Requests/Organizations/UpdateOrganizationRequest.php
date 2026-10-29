<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizations;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use JustSteveKing\Toolbox\MergePatch\MergePatch;

/**
 * A JSON Merge Patch (RFC 7396). The patch is applied to the current organisation, and the merged
 * document is what gets validated: in a patch, null means "remove", which here clears a nullable
 * field and fails a required one (ADR-0004).
 */
final class UpdateOrganizationRequest extends FormRequest
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
        return OrganizationRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return OrganizationRules::messages();
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        $organization = $this->route('organization');

        // Only Scribe reads the rules with no route, to document the body.
        if ( ! $organization instanceof Organization) {
            return [];
        }

        $merged = app(MergePatch::class)->applyTo($organization->only(Organization::FIELDS));

        /** @var array<string, mixed> $document */
        $document = is_array($merged) ? $merged : [];

        return $document;
    }
}
