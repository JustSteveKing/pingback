<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Contacts;

use JustSteveKing\Pingback\Data\Contact;
use JustSteveKing\SdkToolbox\Bodies\Body;
use JustSteveKing\SdkToolbox\Bodies\MergePatch;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Precondition;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\Conditional;
use JustSteveKing\SdkToolbox\Requests\HasBody;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * Changes a contact with a merge patch of only what differs between the version read and
 * the changed copy, sent with If-Match: the original's ETag. If someone changed it in between, the
 * API answers 412 and nothing is overwritten. Needs contacts:write.
 *
 * @implements Request<Contact>
 */
final readonly class UpdateContact implements Conditional, HasBody, Request
{
    public function __construct(
        private Contact $original,
        private Contact $changed,
    ) {}

    public function method(): Method
    {
        return Method::Patch;
    }

    public function path(): string
    {
        return '/contacts/'.rawurlencode($this->original->id);
    }

    public function body(): Body
    {
        return MergePatch::diff($this->original->fields(), $this->changed->fields());
    }

    /**
     * None without an ETag, and the API answers 428: a contact from a list has none.
     */
    public function precondition(): ?Precondition
    {
        return $this->original->etag === null ? null : Precondition::ifMatch($this->original->etag);
    }

    public function result(Response $response): Contact
    {
        return ContactResult::from($response);
    }
}
