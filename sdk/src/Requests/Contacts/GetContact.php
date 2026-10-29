<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Contacts;

use JustSteveKing\Pingback\Data\Contact;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * One contact, deleted or not, with its ETag. Needs contacts:read.
 *
 * @implements Request<Contact>
 */
final readonly class GetContact implements Request
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
        return '/contacts/'.rawurlencode($this->id);
    }

    public function result(Response $response): Contact
    {
        return ContactResult::from($response);
    }
}
