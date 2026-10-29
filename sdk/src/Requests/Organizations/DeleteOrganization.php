<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Organizations;

use JustSteveKing\Pingback\Data\Organization;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Precondition;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\Conditional;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * Deletes an organisation, with If-Match, so nothing is deleted on the strength of a stale read.
 * It is kept and can be restored. Needs organizations:write.
 *
 * @implements Request<null>
 */
final readonly class DeleteOrganization implements Conditional, Request
{
    public function __construct(
        private Organization $organization,
    ) {}

    public function method(): Method
    {
        return Method::Delete;
    }

    public function path(): string
    {
        return '/organizations/'.rawurlencode($this->organization->id);
    }

    public function precondition(): ?Precondition
    {
        return $this->organization->etag === null ? null : Precondition::ifMatch($this->organization->etag);
    }

    public function result(Response $response): null
    {
        return null;
    }
}
