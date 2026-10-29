<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Contact;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('changes only the fields in the patch, and gives the new ETag', function (): void {
    $contact = Contact::factory()->create(['first_name' => 'Ada', 'city' => 'Leeds']);
    $token = contactToken($contact->account);
    $etag = contactEtag($contact, $token);

    $response = mergePatch("/v1/contacts/{$contact->id}", ['first_name' => 'Augusta'], $token, ['If-Match' => $etag])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Augusta')
        ->assertJsonPath('data.city', 'Leeds')
        ->assertETag();

    expect($response->headers->get('ETag'))
        ->not->toBe($etag)
        ->toBe(contactEtag($contact->refresh(), $token));
});

it('clears a nullable field patched to null', function (): void {
    $contact = Contact::factory()->create(['email' => 'hello@rippin.example']);
    $token = contactToken($contact->account);

    mergePatch("/v1/contacts/{$contact->id}", ['email' => null], $token, ['If-Match' => contactEtag($contact, $token)])
        ->assertOk()
        ->assertJsonPath('data.email', null);
});

it('refuses to clear a name', function (): void {
    $contact = Contact::factory()->create();
    $token = contactToken($contact->account);

    mergePatch("/v1/contacts/{$contact->id}", ['last_name' => null], $token, ['If-Match' => contactEtag($contact, $token)])
        ->assertProblem(422)
        ->assertJsonValidationErrors('last_name');
});

it('refuses a stale If-Match with 412, leaving the record alone', function (): void {
    $contact = Contact::factory()->create(['first_name' => 'Ada']);
    $token = contactToken($contact->account);
    $stale = contactEtag($contact, $token);

    mergePatch("/v1/contacts/{$contact->id}", ['first_name' => 'First'], $token, ['If-Match' => $stale])->assertOk();

    mergePatch("/v1/contacts/{$contact->id}", ['first_name' => 'Second'], $token, ['If-Match' => $stale])
        ->assertPreconditionFailed();

    expect($contact->refresh()->first_name)->toBe('First');
});

it('requires If-Match, with 428', function (): void {
    $contact = Contact::factory()->create();

    mergePatch("/v1/contacts/{$contact->id}", ['first_name' => 'Augusta'], contactToken($contact->account))
        ->assertPreconditionRequired();
});

it('accepts only a merge patch', function (): void {
    $contact = Contact::factory()->create();
    $token = contactToken($contact->account);

    $this->withToken($token)
        ->patchJson("/v1/contacts/{$contact->id}", ['first_name' => 'Augusta'], ['If-Match' => contactEtag($contact, $token)])
        ->assertProblem(415);
});

it('treats another account\'s contact as missing (ADR-0003)', function (): void {
    $contact = Contact::factory()->create();

    mergePatch("/v1/contacts/{$contact->id}", ['first_name' => 'Mine'], contactToken(Account::factory()->create()), ['If-Match' => '"anything"'])
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->patchJson('/v1/contacts/01jbd1k9r2w3x4y5z6a7b8c9d0', [])
        ->assertProblem(401);
});

it('requires the contacts:write ability', function (): void {
    $contact = Contact::factory()->create();

    mergePatch("/v1/contacts/{$contact->id}", ['first_name' => 'Augusta'], contactToken($contact->account, ['contacts:read']), ['If-Match' => '"anything"'])
        ->assertProblem(403);
});

it('moves a contact to another organisation, or to none', function (): void {
    $contact = Contact::factory()->create();
    $organization = Organization::factory()->for($contact->account)->create();
    $token = contactToken($contact->account);

    mergePatch("/v1/contacts/{$contact->id}", ['organization_id' => $organization->id], $token, ['If-Match' => contactEtag($contact, $token)])
        ->assertOk()
        ->assertJsonPath('data.organization_id', $organization->id);

    mergePatch("/v1/contacts/{$contact->id}", ['organization_id' => null], $token, ['If-Match' => contactEtag($contact, $token)])
        ->assertOk()
        ->assertJsonPath('data.organization_id', null);
});

it('refuses to move a contact to another account\'s organisation', function (): void {
    $contact = Contact::factory()->create();
    $token = contactToken($contact->account);

    mergePatch("/v1/contacts/{$contact->id}", ['organization_id' => Organization::factory()->create()->id], $token, ['If-Match' => contactEtag($contact, $token)])
        ->assertProblem(422)
        ->assertJsonValidationErrors('organization_id');
});

it('still edits a contact whose organisation has since been deleted', function (): void {
    $organization = Organization::factory()->create();
    $contact = Contact::factory()->for($organization->account)->create(['organization_id' => $organization->id]);
    $organization->delete();
    $token = contactToken($contact->account);

    mergePatch("/v1/contacts/{$contact->id}", ['city' => 'York'], $token, ['If-Match' => contactEtag($contact, $token)])
        ->assertOk()
        ->assertJsonPath('data.organization_id', $organization->id)
        ->assertJsonPath('data.city', 'York');
});
