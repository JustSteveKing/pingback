<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests\Exports;

use JustSteveKing\Pingback\Data\ContactExport;
use JustSteveKing\SdkToolbox\Http\Response;

/**
 * An export from the response that carried its file. It is named by the file name Pingback gives
 * it in Content-Disposition (RFC 6266), contacts-{id}.csv, rather than by the URL it came from: a
 * PSR-18 client that follows redirects itself hands back the file as the answer to the monitor.
 *
 * @internal
 */
final readonly class ExportResult
{
    public static function from(Response $response): ContactExport
    {
        $disposition = (string) $response->header('Content-Disposition');
        $id = preg_match('/filename="?contacts-([0-9A-Za-z]+)\.csv/', $disposition, $match) === 1
            ? $match[1]
            : rawurldecode(basename($response->request()->getUri()->getPath()));

        return new ContactExport($id, $response->body());
    }
}
