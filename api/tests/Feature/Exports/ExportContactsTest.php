<?php

declare(strict_types=1);

use App\Jobs\ExportContacts;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Export;
use App\Models\Organization;
use App\Models\User;
use App\Models\WebhookEvent as StoredEvent;
use App\Models\WebhookSubscription;
use App\Webhooks\Webhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use JustSteveKing\Toolbox\AsyncOperations\CurrentOperation;
use JustSteveKing\Toolbox\AsyncOperations\Operations;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake();
    $this->account = Account::factory()->create();
    $this->token = $this->account->owner->createToken('test', ['exports:create', 'exports:read'])->plainTextToken;
});

/**
 * @return list<list<string>>
 */
function csvRows(string $csv): array
{
    return array_map(static fn(string $line): array => str_getcsv($line, escape: ''), array_values(array_filter(explode("\n", $csv))));
}

it('answers Prefer: respond-async with 202, a monitor and Retry-After', function (): void {
    Queue::fake();

    $response = $this->withToken($this->token)
        ->postJson('/v1/exports', [], ['Prefer' => 'respond-async'])
        ->assertAccepted()
        ->assertHeader('Retry-After', '2')
        ->assertPreferenceApplied('respond-async')
        ->assertJsonPath('status', 'pending');

    $monitor = (string) $response->headers->get('Location');
    expect($monitor)->toBe(route(Operations::ROUTE, $response->json('id')));

    $this->withToken($this->token)->getJson($monitor)
        ->assertOk()
        ->assertHeader('Retry-After', '2')
        ->assertJsonPath('status', 'pending');
});

it('points the monitor at the file once the export is done, and the file is the account\'s live contacts', function (): void {
    $organization = Organization::factory()->for($this->account)->create(['name' => 'Analytical Engines']);
    Contact::factory()->for($this->account)->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'organization_id' => $organization->id]);
    Contact::factory()->for($this->account)->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
    Contact::factory()->for($this->account)->create(['first_name' => 'Deleted'])->delete();
    Contact::factory()->create(['first_name' => 'Another account\'s']);

    $monitor = (string) $this->withToken($this->token)->postJson('/v1/exports', [], ['Prefer' => 'respond-async'])
        ->assertAccepted()
        ->headers->get('Location');

    $file = (string) $this->withToken($this->token)->getJson($monitor)->assertStatus(303)->headers->get('Location');
    $export = Export::query()->sole();
    expect($file)->toBe(route('v1.exports.show', $export));

    $download = $this->withToken($this->token)->get($file)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

    $rows = csvRows($download->streamedContent());

    expect($rows[0])->toBe(['id', 'first_name', 'last_name', 'email', 'phone', 'organization', 'address', 'city', 'region', 'country', 'postal_code'])
        ->and(array_column(array_slice($rows, 1), 1))->toBe(['Grace', 'Ada'])
        ->and($rows[2][5])->toBe('Analytical Engines')
        ->and($export->rows)->toBe(2)
        ->and($export->expires_at?->toAtomString())->toBe($export->completed_at?->addHours(Export::EXPIRES_AFTER_HOURS)->toAtomString());
});

it('waits for the export when async was not asked for, and answers 303 to the file', function (): void {
    $response = $this->withToken($this->token)->postJson('/v1/exports')->assertStatus(303);

    expect($response->headers->get('Location'))->toBe(route('v1.exports.show', Export::query()->sole()));
});

it('stops waiting at the limit and answers 202 anyway', function (): void {
    Queue::fake();

    $this->withToken($this->token)
        ->postJson('/v1/exports', [], ['Prefer' => 'wait=0'])
        ->assertAccepted()
        ->assertHeader('Location');
});

it('starts one export for one Idempotency-Key, however often it is sent', function (): void {
    Queue::fake();

    $first = $this->withToken($this->token)->postJson('/v1/exports', [], ['Prefer' => 'respond-async', 'Idempotency-Key' => '"export-1"'])->assertAccepted();

    $this->withToken($this->token)->postJson('/v1/exports', [], ['Prefer' => 'respond-async', 'Idempotency-Key' => '"export-1"'])
        ->assertIdempotentReplay($first);

    expect(Export::query()->count())->toBe(1);
});

it('announces a finished export', function (): void {
    Queue::fake();
    WebhookSubscription::factory()->for($this->account->owner)->create(['events' => ['export.completed']]);

    $export = Export::factory()->for($this->account)->create();
    new ExportContacts($export->id, 'https://pingback.example/v1/exports/' . $export->id)->handle(app(CurrentOperation::class), app(Webhooks::class));

    $event = StoredEvent::query()->sole();
    $export = Export::query()->sole();

    expect($event->type)->toBe('export.completed')
        ->and($event->subject)->toBe($export->id)
        ->and($event->data)->toBe([
            'id' => $export->id,
            'rows' => 0,
            'url' => 'https://pingback.example/v1/exports/' . $export->id,
            'expires_at' => $export->expires_at?->toAtomString(),
        ]);
});

it('answers an expired export with 410, and removes its file', function (): void {
    Storage::put('exports/old.csv', "id\n");
    $export = Export::factory()->for($this->account)->create(['path' => 'exports/old.csv', 'rows' => 0, 'completed_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);

    $this->withToken($this->token)->getJson("/v1/exports/{$export->id}")
        ->assertProblem(410)
        ->assertJsonPath('detail', __('api.exports.expired'));

    Storage::assertMissing('exports/old.csv');
});

it('has no file for an export that has not finished', function (): void {
    $export = Export::factory()->for($this->account)->create();

    $this->withToken($this->token)->getJson("/v1/exports/{$export->id}")->assertProblem(404);
});

it('treats another account\'s export, and another user\'s operation, as missing', function (): void {
    Queue::fake();
    $other = Account::factory()->create();
    $otherToken = $other->owner->createToken('test', ['exports:create', 'exports:read'])->plainTextToken;

    $monitor = (string) $this->withToken($this->token)->postJson('/v1/exports', [], ['Prefer' => 'respond-async'])->headers->get('Location');
    $export = Export::factory()->for($this->account)->create(['path' => 'exports/x.csv', 'completed_at' => now(), 'expires_at' => now()->addDay()]);

    // The guard keeps the first request's user for the rest of the test unless told to forget.
    app('auth')->forgetGuards();

    $this->withToken($otherToken)->getJson($monitor)->assertProblem(404);
    $this->withToken($otherToken)->getJson("/v1/exports/{$export->id}")->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->postJson('/v1/exports')->assertProblem(401);
    $this->getJson('/v1/exports/01jbd2c3e4f5g6h7j8k9m0n1p2')->assertProblem(401);
});

it('requires the export abilities', function (): void {
    $token = User::factory()->create()->createToken('test', ['auth:me'])->plainTextToken;

    $this->withToken($token)->postJson('/v1/exports')->assertProblem(403);
    $this->withToken($token)->getJson('/v1/exports/01jbd2c3e4f5g6h7j8k9m0n1p2')->assertProblem(403);
    $this->withToken($token)->getJson('/v1/operations/01jbd2c3e4f5g6h7j8k9m0n1p2')->assertProblem(403);
});

it('tells the job where the file will be on the host the client used, not APP_URL', function (): void {
    Queue::fake();
    config(['app.url' => 'https://elsewhere.example']);

    $this->withToken($this->token)->postJson('http://127.0.0.1:8000/v1/exports', [], ['Prefer' => 'respond-async'])->assertAccepted();

    Queue::assertPushed(ExportContacts::class, static fn(ExportContacts $job): bool => str_starts_with($job->url, 'http://127.0.0.1:8000/v1/exports/'));
});
