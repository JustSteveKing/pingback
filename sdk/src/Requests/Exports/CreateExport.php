<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Exports;

use JustSteveKing\Pingback\Data\ContactExport;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Preferences;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\HasPreferences;
use JustSteveKing\SdkToolbox\Requests\Idempotent;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * Starts an export of the account's contacts (ADR-0005). It prefers respond-async (RFC 7240), so
 * the API answers 202 at once with its status monitor, which await() follows to the file. It is
 * idempotent, so a retried start runs one export. Needs exports:create, and exports:read to fetch
 * the monitor and the file.
 *
 * @implements Request<ContactExport>
 */
final readonly class CreateExport implements HasPreferences, Idempotent, Request
{
    public function __construct(
        private ?string $idempotencyKey = null,
    ) {}

    public function method(): Method
    {
        return Method::Post;
    }

    public function path(): string
    {
        return '/exports';
    }

    public function preferences(): Preferences
    {
        return Preferences::none()->respondAsync();
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function result(Response $response): ContactExport
    {
        return ExportResult::from($response);
    }
}
