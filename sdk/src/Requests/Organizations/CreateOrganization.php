<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Organizations;

use JustSteveKing\Pingback\Data\Organization;
use JustSteveKing\Pingback\Inputs\NewOrganization;
use JustSteveKing\SdkToolbox\Bodies\Body;
use JustSteveKing\SdkToolbox\Bodies\JsonBody;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\HasBody;
use JustSteveKing\SdkToolbox\Requests\Idempotent;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * Creates an organisation. It is idempotent: every retry of one send carries the same
 * Idempotency-Key, so a retry after a timeout gets the first response back rather than a second
 * organisation. Pass a key of your own to make that hold across sends too. Needs
 * organizations:write.
 *
 * @implements Request<Organization>
 */
final readonly class CreateOrganization implements HasBody, Idempotent, Request
{
    public function __construct(
        private NewOrganization $organization,
        private ?string $idempotencyKey = null,
    ) {}

    public function method(): Method
    {
        return Method::Post;
    }

    public function path(): string
    {
        return '/organizations';
    }

    public function body(): Body
    {
        return new JsonBody($this->organization->toArray());
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function result(Response $response): Organization
    {
        return OrganizationResult::from($response);
    }
}
