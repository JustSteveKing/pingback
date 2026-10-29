<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Exports;

use App\Jobs\ExportContacts;
use App\Models\Export;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Sleep;
use JustSteveKing\Toolbox\AsyncOperations\Operations;
use JustSteveKing\Toolbox\Preferences\Preferences;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;

#[Group(name: 'Exports')]
#[Endpoint(
    title: 'Create Export',
    description: 'Export the account\'s contacts, deleted ones left out, to CSV. Send Prefer: respond-async to get 202 Accepted at once, with the status monitor in Location and Retry-After; without it the request waits up to 5 seconds and answers 303 See Other to the file, or 202 if it is still running. Send an Idempotency-Key, so a retried start runs one export, not two.',
)]
#[Authenticated]
#[Header('Prefer', 'respond-async')]
#[Header('Idempotency-Key', '"export-retry-1"')]
#[Response(content: ['id' => '01jbd2c3e4f5g6h7j8k9m0n1p2', 'status' => 'pending', 'created_at' => '2026-10-29T09:00:00+00:00', 'updated_at' => '2026-10-29T09:00:00+00:00'], status: 202, description: 'The export is running: poll the monitor in Location.')]
#[Response(content: null, status: 303, description: 'The export finished while the request waited: the file is in Location.')]
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
    description: 'Token is missing the exports:create ability.',
)]
final class CreateExportController
{
    public function __invoke(#[CurrentUser] User $user, Operations $operations, Preferences $preferences): Responsable|RedirectResponse
    {
        $export = $user->account()->firstOrFail()->exports()->create();
        $accepted = $operations->dispatch(new ExportContacts($export->id, route('v1.exports.show', $export)));

        if ($preferences->respondAsync()) {
            return $accepted;
        }

        // ADR-0005: a client that did not ask for async waits, up to a limit, and gets the 202
        // after that anyway. Prefer: wait shortens the limit, never lengthens it.
        $deadline = microtime(true) + min($preferences->wait() ?? Export::WAIT_SECONDS, Export::WAIT_SECONDS);

        do {
            if ($export->refresh()->completed()) {
                return new RedirectResponse(route('v1.exports.show', $export), 303);
            }

            Sleep::usleep(200_000);
        } while (microtime(true) < $deadline);

        return $accepted;
    }
}
