<?php

declare(strict_types=1);

namespace App\Webhooks\Events;

use App\Http\Resources\ContactResource;
use App\Http\Resources\OrganizationResource;
use App\Models\Contact;
use App\Models\Organization;
use App\Webhooks\WebhookEvent;

/**
 * An organisation or contact was created, updated, deleted or restored. The subject is its ID and
 * the data is the record as the API sends it after the change, so a subscriber needs no follow-up
 * read; a deleted record's deleted_at says so.
 */
final readonly class RecordChanged implements WebhookEvent
{
    /**
     * Every type this event is sent as, for config/webhooks.php.
     */
    public const array TYPES = [
        'organization.created', 'organization.updated', 'organization.deleted', 'organization.restored',
        'contact.created', 'contact.updated', 'contact.deleted', 'contact.restored',
    ];

    /**
     * @param  array<string, mixed>  $record
     */
    private function __construct(
        private string $type,
        private string $subject,
        private array $record,
    ) {}

    /**
     * @param  'created'|'updated'|'deleted'|'restored'  $change
     */
    public static function organization(string $change, Organization $organization): self
    {
        return new self("organization.{$change}", $organization->id, self::fields(OrganizationResource::make($organization)->resolve()));
    }

    /**
     * @param  'created'|'updated'|'deleted'|'restored'  $change
     */
    public static function contact(string $change, Contact $contact): self
    {
        return new self("contact.{$change}", $contact->id, self::fields(ContactResource::make($contact)->resolve()));
    }

    public function type(): string
    {
        return $this->type;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->record;
    }

    /**
     * @param  array<array-key, mixed>  $resolved
     * @return array<string, mixed>
     */
    private static function fields(array $resolved): array
    {
        $fields = [];

        foreach ($resolved as $key => $value) {
            $fields[(string) $key] = $value;
        }

        return $fields;
    }
}
