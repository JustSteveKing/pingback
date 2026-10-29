<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Organizations;

use JustSteveKing\Pingback\Data\Organization;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * One organisation, deleted or not, with its ETag. Needs organizations:read.
 *
 * @implements Request<Organization>
 */
final readonly class GetOrganization implements Request
{
    public function __construct(
        private string $id,
    ) {}

    public function method(): Method
    {
        return Method::Get;
    }

    public function path(): string
    {
        return '/organizations/'.rawurlencode($this->id);
    }

    public function result(Response $response): Organization
    {
        return OrganizationResult::from($response);
    }
}
