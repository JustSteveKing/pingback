<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Webhooks;

use JustSteveKing\Pingback\Data\Contact;
use JustSteveKing\Pingback\Data\ExportFile;
use JustSteveKing\Pingback\Data\Organization;
use JustSteveKing\SdkToolbox\Hydration\Hydrator;
use LogicException;

/**
 * One verified delivery: a CloudEvents 1.0 event, in structured mode, as Pingback sends them.
 *
 *     match ($event->type) {
 *         'organization.created' => $crm->add($event->organization()),
 *         'export.completed' => $downloads->fetch($event->export()->url),
 *         default => null,
 *     };
 *
 * The record events carry the record as it was after the change, so a deleted record's deletedAt
 * says so.
 */
final readonly class Event
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $source,
        public ?string $subject,
        public string $time,
        public array $data,
        private Hydrator $hydrator,
    ) {}

    /**
     * The organisation an organization.* event is about.
     */
    public function organization(): Organization
    {
        return $this->hydrator->hydrate(Organization::class, $this->expect('organization.'));
    }

    /**
     * The contact a contact.* event is about.
     */
    public function contact(): Contact
    {
        return $this->hydrator->hydrate(Contact::class, $this->expect('contact.'));
    }

    /**
     * The finished export an export.completed event is about.
     */
    public function export(): ExportFile
    {
        return $this->hydrator->hydrate(ExportFile::class, $this->expect('export.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function expect(string $prefix): array
    {
        if (! str_starts_with($this->type, $prefix)) {
            throw new LogicException("A {$this->type} event isn't about {$prefix}*.");
        }

        return $this->data;
    }
}
