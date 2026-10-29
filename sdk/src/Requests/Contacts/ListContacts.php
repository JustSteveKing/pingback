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

    /**
     * The contacts at one organisation are their own resource. The organization_id filter on
     * /contacts that came before it is deprecated (Pingback's ADR-0008), so it is never sent.
     */
    public function path(): string
    {
        return $this->organizationId === null ? '/contacts' : '/organizations/'.rawurlencode($this->organizationId).'/contacts';
    }

    public function query(): array
    {
        return [
            'search' => $this->search,
            'trashed' => $this->trashed?->value,
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
