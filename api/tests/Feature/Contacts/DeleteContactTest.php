<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deletes an contact, keeping it to restore', function (): void {
    $contact = Contact::factory()->create();
    $token = contactToken($contact->account);
    $etag = contactEtag($contact, $token);

    $this->withToken($token)
        ->deleteJson("/v1/contacts/{$contact->id}", [], ['If-Match' => $etag])
        ->assertNoContent();

    expect($contact->refresh()->trashed())->toBeTrue()
        ->and(contactEtag($contact, $token))->not->toBe($etag);
});

it('changes nothing when deleted again', function (): void {
    $contact = Contact::factory()->create();
    $contact->delete();
    $deletedAt = $contact->refresh()->deleted_at?->toAtomString();
    $token = contactToken($contact->account);

    $this->travel(1)->minute();

    $this->withToken($token)
        ->deleteJson("/v1/contacts/{$contact->id}", [], ['If-Match' => contactEtag($contact, $token)])
        ->assertNoContent();

    expect($contact->refresh()->deleted_at?->toAtomString())->toBe($deletedAt);
});

it('refuses a stale If-Match with 412', function (): void {
    $contact = Contact::factory()->create();
    $token = contactToken($contact->account);
    $stale = contactEtag($contact, $token);
    $contact->update(['first_name' => 'Changed']);

    $this->withToken($token)
        ->deleteJson("/v1/contacts/{$contact->id}", [], ['If-Match' => $stale])
        ->assertPreconditionFailed();

    expect($contact->refresh()->trashed())->toBeFalse();
});

it('requires If-Match, with 428', function (): void {
    $contact = Contact::factory()->create();

    $this->withToken(contactToken($contact->account))
        ->deleteJson("/v1/contacts/{$contact->id}")
        ->assertPreconditionRequired();
});

it('treats another account\'s contact as missing (ADR-0003)', function (): void {
    $contact = Contact::factory()->create();

    $this->withToken(contactToken(Account::factory()->create()))
        ->deleteJson("/v1/contacts/{$contact->id}", [], ['If-Match' => '"anything"'])
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->deleteJson('/v1/contacts/01jbd1k9r2w3x4y5z6a7b8c9d0')
        ->assertProblem(401);
});

it('requires the contacts:write ability', function (): void {
    $contact = Contact::factory()->create();

    $this->withToken(contactToken($contact->account, ['contacts:read']))
        ->deleteJson("/v1/contacts/{$contact->id}", [], ['If-Match' => '"anything"'])
        ->assertProblem(403);
});
