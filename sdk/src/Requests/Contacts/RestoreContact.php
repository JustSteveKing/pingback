<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Contacts;

use JustSteveKing\Pingback\Data\Contact;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * Brings back a deleted contact. Restoring one that is not deleted changes nothing, so it is
 * safe to retry. Needs contacts:write.
 *
 * @implements Request<Contact>
 */
final readonly class RestoreContact implements Request
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
        return '/contacts/'.rawurlencode($this->id).'/restore';
    }

    public function result(Response $response): Contact
    {
        return ContactResult::from($response);
    }
}
