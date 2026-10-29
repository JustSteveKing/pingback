<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('restores a deleted contact, with a new ETag', function (): void {
    $contact = Contact::factory()->create();
    $token = contactToken($contact->account);
    $before = contactEtag($contact, $token);
    $contact->delete();

    $response = $this->withToken($token)
        ->postJson("/v1/contacts/{$contact->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.deleted_at', null)
        ->assertETag();

    expect($contact->refresh()->trashed())->toBeFalse()
        ->and($response->headers->get('ETag'))->not->toBe($before);
});

it('changes nothing for an contact that is not deleted', function (): void {
    $contact = Contact::factory()->create();
    $token = contactToken($contact->account);
    $etag = contactEtag($contact, $token);

    $this->withToken($token)
        ->postJson("/v1/contacts/{$contact->id}/restore")
        ->assertOk()
        ->assertETag($etag);
});

it('treats another account\'s contact as missing (ADR-0003)', function (): void {
    $contact = Contact::factory()->create();
    $contact->delete();

    $this->withToken(contactToken(Account::factory()->create()))
        ->postJson("/v1/contacts/{$contact->id}/restore")
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->postJson('/v1/contacts/01jbd1k9r2w3x4y5z6a7b8c9d0/restore')
        ->assertProblem(401);
});

it('requires the contacts:write ability', function (): void {
    $contact = Contact::factory()->create();

    $this->withToken(contactToken($contact->account, ['contacts:read']))
        ->postJson("/v1/contacts/{$contact->id}/restore")
        ->assertProblem(403);
});
