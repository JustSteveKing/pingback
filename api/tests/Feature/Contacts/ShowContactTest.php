<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows an contact with its ETag', function (): void {
    $contact = Contact::factory()->create(['first_name' => 'Ada']);

    $this->withToken(contactToken($contact->account))
        ->getJson("/v1/contacts/{$contact->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $contact->id)
        ->assertJsonPath('data.first_name', 'Ada')
        ->assertETag();
});

it('answers a matching If-None-Match with 304', function (): void {
    $contact = Contact::factory()->create();
    $token = contactToken($contact->account);

    $this->withToken($token)
        ->getJson("/v1/contacts/{$contact->id}", ['If-None-Match' => contactEtag($contact, $token)])
        ->assertNotModified();
});

it('shows a deleted contact, marked deleted', function (): void {
    $contact = Contact::factory()->create();
    $contact->delete();

    $this->withToken(contactToken($contact->account))
        ->getJson("/v1/contacts/{$contact->id}")
        ->assertOk()
        ->assertJsonPath('data.deleted_at', $contact->deleted_at?->toAtomString());
});

it('treats another account\'s contact as missing (ADR-0003)', function (): void {
    $contact = Contact::factory()->create();

    $this->withToken(contactToken(Account::factory()->create()))
        ->getJson("/v1/contacts/{$contact->id}")
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->getJson('/v1/contacts/01jbd1k9r2w3x4y5z6a7b8c9d0')
        ->assertProblem(401);
});

it('requires the contacts:read ability', function (): void {
    $token = User::factory()->create()->createToken('test', ['auth:me'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/v1/contacts/01jbd1k9r2w3x4y5z6a7b8c9d0')
        ->assertProblem(403);
});
