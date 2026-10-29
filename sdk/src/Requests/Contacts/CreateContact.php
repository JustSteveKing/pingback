<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Contacts;

use JustSteveKing\Pingback\Data\Contact;
use JustSteveKing\Pingback\Inputs\NewContact;
use JustSteveKing\SdkToolbox\Bodies\Body;
use JustSteveKing\SdkToolbox\Bodies\JsonBody;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\HasBody;
use JustSteveKing\SdkToolbox\Requests\Idempotent;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * Creates a contact. It is idempotent: every retry of one send carries the same
 * Idempotency-Key, so a retry after a timeout gets the first response back rather than a second
 * contact. Pass a key of your own to make that hold across sends too. Needs
 * contacts:write.
 *
 * @implements Request<Contact>
 */
final readonly class CreateContact implements HasBody, Idempotent, Request
{
    public function __construct(
        private NewContact $contact,
        private ?string $idempotencyKey = null,
    ) {}

    public function method(): Method
    {
        return Method::Post;
    }

    public function path(): string
    {
        return '/contacts';
    }

    public function body(): Body
    {
        return new JsonBody($this->contact->toArray());
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function result(Response $response): Contact
    {
        return ContactResult::from($response);
    }
}
