<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Exports;

use App\Models\Export;
use Illuminate\Support\Facades\Storage;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group(name: 'Exports')]
#[Endpoint(
    title: 'Show Export',
    description: 'Download a finished contact export as CSV, with a header row. Follow the 303 from the status monitor, or the url in the export.completed webhook, to get here. Returns the file until it expires, a day after it finished, and 410 Gone after that.',
)]
#[UrlParam('export', type: 'string', description: 'The export ID, a ULID.', required: true, example: '01jbd2c3e4f5g6h7j8k9m0n1p2')]
#[Authenticated]
#[Response(content: "id,first_name,last_name,email,phone,organization,address,city,region,country,postal_code\n", status: 200, description: 'The CSV file.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'The resource was not found.',
    ],
    status: 404,
    description: 'No finished export with this ID belongs to the authenticated account.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/gone',
        'title' => 'Gone',
        'status' => 410,
        'detail' => 'This export has expired. Start another.',
    ],
    status: 410,
    description: 'The export expired.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/unauthorized',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
    ],
    status: 401,
    description: 'Authentication failed.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'Forbidden.',
    ],
    status: 403,
    description: 'Token is missing the exports:read ability.',
)]
final class ShowExportController
{
    public function __invoke(Export $export): StreamedResponse
    {
        if ( ! $export->completed() || null === $export->path) {
            abort(404, __('api.errors.not_found'));
        }

        if ($export->expired()) {
            Storage::delete($export->path);

            abort(410, __('api.exports.expired'));
        }

        return Storage::download($export->path, "contacts-{$export->id}.csv", ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
