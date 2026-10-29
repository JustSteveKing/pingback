<?php

declare(strict_types=1);

use JustSteveKing\Pingback\Pingback;
use JustSteveKing\SdkToolbox\Errors\OperationFailedException;
use JustSteveKing\SdkToolbox\Testing\FakeClient;
use JustSteveKing\SdkToolbox\Testing\FakeResponse;
use JustSteveKing\SdkToolbox\Testing\FakeTime;

function exporting(FakeClient $http, FakeTime $time): Pingback
{
    return new Pingback('a-token', 'http://127.0.0.1:8000/v1', $http, clock: $time, sleeper: $time);
}

it('asks for respond-async, polls the monitor as Retry-After says, and reads the file', function (): void {
    $time = new FakeTime;
    $http = new FakeClient(
        FakeResponse::json(['id' => 'op_1', 'status' => 'pending'], 202, ['Location' => 'http://127.0.0.1:8000/v1/operations/op_1', 'Retry-After' => '2', 'Preference-Applied' => 'respond-async']),
        FakeResponse::json(['id' => 'op_1', 'status' => 'running'], 200, ['Retry-After' => '2']),
        FakeResponse::empty(303, ['Location' => 'http://127.0.0.1:8000/v1/exports/01m63qf6c1yvphnafgx4qc8n2j']),
        FakeResponse::text("id,first_name,last_name,organization\n01m63,Ada,Lovelace,\"Engines, Ltd\"\n", headers: ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename=contacts-01m63qf6c1yvphnafgx4qc8n2j.csv']),
    );

    $export = exporting($http, $time)->exports()->create();

    expect($http->sent()[0]->getHeaderLine('Prefer'))->toBe('respond-async')
        ->and($http->sent()[0]->hasHeader('Idempotency-Key'))->toBeTrue()
        ->and($time->sleeps())->toBe([2.0, 2.0])
        ->and($export->id)->toBe('01m63qf6c1yvphnafgx4qc8n2j')
        ->and($export->rows())->toBe([['id' => '01m63', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'organization' => 'Engines, Ltd']]);
});

it('throws OperationFailedException when the API reports the export failed', function (): void {
    $http = new FakeClient(
        FakeResponse::json(['id' => 'op_1', 'status' => 'pending'], 202, ['Location' => 'http://127.0.0.1:8000/v1/operations/op_1']),
        FakeResponse::json(['id' => 'op_1', 'status' => 'failed', 'error' => ['type' => 'https://apiguide.dev/errors/internal-server-error', 'title' => 'Internal Server Error', 'status' => 500]]),
    );

    expect(fn () => exporting($http, new FakeTime)->exports()->create())->toThrow(OperationFailedException::class);
});

it('names the export by its file, even when the HTTP client followed the redirect itself', function (): void {
    $http = new FakeClient(
        FakeResponse::json(['id' => 'op_1', 'status' => 'pending'], 202, ['Location' => 'http://127.0.0.1:8000/v1/operations/op_1']),
        // What a redirect-following client hands back for the monitor: the file itself.
        FakeResponse::text("id\n", headers: ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename=contacts-01m63qf6c1yvphnafgx4qc8n2j.csv']),
    );

    expect(exporting($http, new FakeTime)->exports()->create()->id)->toBe('01m63qf6c1yvphnafgx4qc8n2j');
});
