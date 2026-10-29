<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Operations;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\AsyncOperations\Http\ShowOperation;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * api-toolbox's status monitor, registered by Pingback ('route' => false) so that it is documented
 * like the rest of the API; the behaviour is all api-toolbox's.
 */
#[Group(name: 'Exports')]
#[Endpoint(
    title: 'Show Operation',
    description: 'The status monitor a 202 Accepted points at in Location. Poll it no sooner than its Retry-After says. Returns 200 with the status while pending or running, 303 See Other to the result when it succeeded, and 200 with a problem in error when it failed.',
)]
#[UrlParam('operation', type: 'string', description: 'The operation ID, from the 202\'s Location.', required: true, example: '01jbd2c3e4f5g6h7j8k9m0n1p2')]
#[Authenticated]
#[Response(content: ['id' => '01jbd2c3e4f5g6h7j8k9m0n1p2', 'status' => 'running', 'created_at' => '2026-10-29T09:00:00+00:00', 'updated_at' => '2026-10-29T09:00:01+00:00'], status: 200, description: 'Still running: ask again after Retry-After.')]
#[Response(content: null, status: 303, description: 'Finished: the result is in Location.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'Operation not found.',
    ],
    status: 404,
    description: 'No operation with this ID was started by the authenticated user.',
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
final class ShowOperationController
{
    public function __invoke(Request $request, string $operation, ShowOperation $monitor): JsonResponse|RedirectResponse
    {
        return $monitor($request, $operation);
    }
}
