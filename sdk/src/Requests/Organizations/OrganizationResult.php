<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Organizations;

use JustSteveKing\Pingback\Data\Organization;
use JustSteveKing\SdkToolbox\Http\Response;

/**
 * An organisation from a response that carries one, with the response's ETag.
 *
 * @internal
 */
final readonly class OrganizationResult
{
    public static function from(Response $response): Organization
    {
        return $response->map(Organization::class, at: 'data')->withEtag($response->etag());
    }
}
