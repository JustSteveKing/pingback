<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Contacts;

use JustSteveKing\Pingback\Data\Contact;
use JustSteveKing\Pingback\Inputs\Trashed;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\HasQuery;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * One page of the account's contacts, by last name, then first name. The next page is in the
 * Link header, which keeps the search and filters. Needs contacts:read.
 *
 * @implements Request<list<Contact>>
 */
final readonly class ListContacts implements HasQuery, Request
{
    public function __construct(
        private ?string $search = null,
        private ?Trashed $trashed = null,
        private ?string $organizationId = null,
    ) {}

    public function method(): Method
    {
        return Method::Get;
    }

    public function path(): string
    {
        return '/contacts';
    }

    public function query(): array
    {
        return [
            'search' => $this->search,
            'trashed' => $this->trashed?->value,
            'organization_id' => $this->organizationId,
        ];
    }

    /**
     * Listed contacts carry no ETag: one is per resource, so get() one before changing it.
     */
    public function result(Response $response): array
    {
        return $response->mapList(Contact::class);
    }
}
