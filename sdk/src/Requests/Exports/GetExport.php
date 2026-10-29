<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Exports;

use JustSteveKing\Pingback\Data\ContactExport;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * Downloads a finished export. One that has expired is a 410, so a GoneException. Needs
 * exports:read.
 *
 * @implements Request<ContactExport>
 */
final readonly class GetExport implements Request
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
        return '/exports/'.rawurlencode($this->id);
    }

    public function result(Response $response): ContactExport
    {
        return ExportResult::from($response);
    }
}
