<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Contacts;

use JustSteveKing\Pingback\Data\Contact;
use JustSteveKing\SdkToolbox\Http\Response;

/**
 * A contact from a response that carries one, with the response's ETag.
 *
 * @internal
 */
final readonly class ContactResult
{
    public static function from(Response $response): Contact
    {
        return $response->map(Contact::class, at: 'data')->withEtag($response->etag());
    }
}
