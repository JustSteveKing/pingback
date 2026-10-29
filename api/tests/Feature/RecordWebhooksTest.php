<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\WebhookEvent as StoredEvent;
use App\Models\WebhookSubscription;
use App\Webhooks\DeliverWebhook;
use App\Webhooks\Events\ExportCompleted;
use App\Webhooks\Events\RecordChanged;
use App\Webhooks\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * @return list<array{type: string, subject: string|null}>
 */
function sentEvents(): array
{
    return StoredEvent::query()->orderBy('id')->get()
        ->map(static fn(StoredEvent $event): array => ['type' => $event->type, 'subject' => $event->subject])
        ->all();
}

beforeEach(function (): void {
    Queue::fake();
    $this->account = Account::factory()->create();
    WebhookSubscription::factory()->for($this->account->owner)->create(['events' => ['*']]);
    $this->token = $this->account->owner->createToken('test', ['organizations:read', 'organizations:write', 'contacts:read', 'contacts:write'])->plainTextToken;
});

it('announces every change to a record, with the record as it is after it', function (string $resource, array $body, string $field): void {
    $id = $this->withToken($this->token)->postJson("/v1/{$resource}", $body)->assertCreated()->json('data.id');
    $etag = fn(): string => (string) $this->withToken($this->token)->getJson("/v1/{$resource}/{$id}")->headers->get('ETag');

    mergePatch("/v1/{$resource}/{$id}", ['city' => 'York'], $this->token, ['If-Match' => $etag()])->assertOk();
    $this->withToken($this->token)->deleteJson("/v1/{$resource}/{$id}", [], ['If-Match' => $etag()])->assertNoContent();
    $this->withToken($this->token)->postJson("/v1/{$resource}/{$id}/restore")->assertOk();

    $type = 'organizations' === $resource ? 'organization' : 'contact';

    expect(sentEvents())->toBe([
        ['type' => "{$type}.created", 'subject' => $id],
        ['type' => "{$type}.updated", 'subject' => $id],
        ['type' => "{$type}.deleted", 'subject' => $id],
        ['type' => "{$type}.restored", 'subject' => $id],
    ]);

    $events = StoredEvent::query()->orderBy('id')->get();
    expect($events[0]->data[$field])->toBe($body[$field])
        ->and($events[1]->data['city'])->toBe('York')
        ->and($events[2]->data['deleted_at'])->not->toBeNull()
        ->and($events[3]->data['deleted_at'])->toBeNull();

    Queue::assertPushed(DeliverWebhook::class, 4);
})->with([
    'organisations' => ['organizations', ['name' => 'Rippin Group'], 'name'],
    'contacts' => ['contacts', ['first_name' => 'Ada', 'last_name' => 'Lovelace'], 'first_name'],
]);

it('announces nothing when nothing changed', function (): void {
    $organization = Organization::factory()->for($this->account)->create(['city' => 'York']);
    $etag = etagOf($organization, $this->token);

    mergePatch("/v1/organizations/{$organization->id}", ['city' => 'York'], $this->token, ['If-Match' => $etag])->assertOk();

    $contact = Contact::factory()->for($this->account)->create();
    $contact->delete();
    $this->withToken($this->token)->deleteJson("/v1/contacts/{$contact->id}", [], ['If-Match' => contactEtag($contact, $this->token)])->assertNoContent();
    $this->withToken($this->token)->postJson("/v1/organizations/{$organization->id}/restore")->assertOk();

    expect(sentEvents())->toBe([]);
});

it('announces a refused change to nobody', function (): void {
    $organization = Organization::factory()->for($this->account)->create();

    mergePatch("/v1/organizations/{$organization->id}", ['name' => null], $this->token, ['If-Match' => etagOf($organization, $this->token)])->assertProblem(422);
    mergePatch("/v1/organizations/{$organization->id}", ['name' => 'Stale'], $this->token, ['If-Match' => '"stale"'])->assertPreconditionFailed();

    expect(sentEvents())->toBe([]);
});

it('lets a subscription ask for every event Pingback sends', function (string $type): void {
    app()->instance(HostResolver::class, new class implements HostResolver {
        public function addresses(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
    $token = $this->account->owner->createToken('test', ['webhooks:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/v1/webhooks', ['url' => 'https://hooks.example.com/pingback', 'events' => [$type]])
        ->assertCreated();
})->with([...RecordChanged::TYPES, ExportCompleted::TYPE]);
