<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Contacts;

use JustSteveKing\Pingback\Data\Contact;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Precondition;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\Conditional;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * Deletes a contact, with If-Match, so nothing is deleted on the strength of a stale read.
 * It is kept and can be restored. Needs contacts:write.
 *
 * @implements Request<null>
 */
final readonly class DeleteContact implements Conditional, Request
{
    public function __construct(
        private Contact $contact,
    ) {}

    public function method(): Method
    {
        return Method::Delete;
    }

    public function path(): string
    {
        return '/contacts/'.rawurlencode($this->contact->id);
    }

    public function precondition(): ?Precondition
    {
        return $this->contact->etag === null ? null : Precondition::ifMatch($this->contact->etag);
    }

    public function result(Response $response): null
    {
        return null;
    }
}
