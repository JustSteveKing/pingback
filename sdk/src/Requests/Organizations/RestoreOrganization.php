<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Organizations;

use JustSteveKing\Pingback\Data\Organization;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * Brings back a deleted organisation. Restoring one that is not deleted changes nothing, so it is
 * safe to retry. Needs organizations:write.
 *
 * @implements Request<Organization>
 */
final readonly class RestoreOrganization implements Request
{
    public function __construct(
        private string $id,
    ) {}

    public function method(): Method
    {
        return Method::Post;
    }

    public function path(): string
    {
        return '/organizations/'.rawurlencode($this->id).'/restore';
    }

    public function result(Response $response): Organization
    {
        return OrganizationResult::from($response);
    }
}
