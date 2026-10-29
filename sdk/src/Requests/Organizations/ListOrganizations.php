<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Organizations;

use JustSteveKing\Pingback\Data\Organization;
use JustSteveKing\Pingback\Inputs\Trashed;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\HasQuery;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * One page of the account's organisations, by name. The next page is in the Link header, which
 * keeps the search and filter. Needs organizations:read.
 *
 * @implements Request<list<Organization>>
 */
final readonly class ListOrganizations implements HasQuery, Request
{
    public function __construct(
        private ?string $search = null,
        private ?Trashed $trashed = null,
    ) {}

    public function method(): Method
    {
        return Method::Get;
    }

    public function path(): string
    {
        return '/organizations';
    }

    public function query(): array
    {
        return [
            'search' => $this->search,
            'trashed' => $this->trashed?->value,
        ];
    }

    /**
     * Listed organisations carry no ETag: one is per resource, so get() one before changing it.
     */
    public function result(Response $response): array
    {
        return $response->mapList(Organization::class);
    }
}
