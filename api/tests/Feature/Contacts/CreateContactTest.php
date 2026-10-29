<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Contact;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a contact in the account', function (): void {
    $account = Account::factory()->create();

    $response = $this->withToken(contactToken($account))
        ->postJson('/v1/contacts', ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'country' => 'GB', 'city' => 'Leeds'])
        ->assertCreated()
        ->assertJsonPath('data.first_name', 'Ada')
        ->assertJsonPath('data.country', 'GB')
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.deleted_at', null)
        ->assertETag();

    $contact = Contact::query()->findOrFail($response->json('data.id'));

    expect($contact->account_id)->toBe($account->id)
        ->and($response->headers->get('Location'))->toBe(route('v1.contacts.show', $contact));
});

it('gives the ETag a read would give', function (): void {
    $account = Account::factory()->create();
    $token = contactToken($account);

    $created = $this->withToken($token)->postJson('/v1/contacts', ['first_name' => 'Ada', 'last_name' => 'Lovelace'])->assertCreated();

    expect($created->headers->get('ETag'))
        ->toBe(contactEtag(Contact::query()->findOrFail($created->json('data.id')), $token));
});

it('replays a retry with the same Idempotency-Key rather than creating twice', function (): void {
    $account = Account::factory()->create();
    $token = contactToken($account);

    $original = $this->withToken($token)
        ->postJson('/v1/contacts', ['first_name' => 'Ada', 'last_name' => 'Lovelace'], ['Idempotency-Key' => '"contact-1"'])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('/v1/contacts', ['first_name' => 'Ada', 'last_name' => 'Lovelace'], ['Idempotency-Key' => '"contact-1"'])
        ->assertIdempotentReplay($original);

    expect(Contact::query()->count())->toBe(1);
});

it('refuses a contact with no last name or a malformed country', function (): void {
    $account = Account::factory()->create();

    $this->withToken(contactToken($account))
        ->postJson('/v1/contacts', ['first_name' => 'Ada', 'country' => 'gb'])
        ->assertProblem(422)
        ->assertJsonValidationErrors(['last_name', 'country'])
        ->assertJsonPath('errors.country.0', __('api.organizations.country_format'));
});

it('requires authentication', function (): void {
    $this->postJson('/v1/contacts', [])
        ->assertProblem(401);
});

it('requires the contacts:write ability', function (): void {
    $account = Account::factory()->create();

    $this->withToken(contactToken($account, ['contacts:read']))
        ->postJson('/v1/contacts', ['first_name' => 'Ada'])
        ->assertProblem(403);
});

it('puts the contact at one of the account\'s organisations', function (): void {
    $account = Account::factory()->create();
    $organization = Organization::factory()->for($account)->create();

    $this->withToken(contactToken($account))
        ->postJson('/v1/contacts', ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'organization_id' => $organization->id])
        ->assertCreated()
        ->assertJsonPath('data.organization_id', $organization->id);
});

it('refuses another account\'s organisation, or a deleted one, alike', function (bool $deleted): void {
    $account = Account::factory()->create();
    $organization = Organization::factory()->for($deleted ? $account : Account::factory()->create())->create();

    if ($deleted) {
        $organization->delete();
    }

    $this->withToken(contactToken($account))
        ->postJson('/v1/contacts', ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'organization_id' => $organization->id])
        ->assertProblem(422)
        ->assertJsonPath('errors.organization_id.0', __('api.contacts.organization_unknown'));
})->with(['another account\'s' => false, 'a deleted one' => true]);
