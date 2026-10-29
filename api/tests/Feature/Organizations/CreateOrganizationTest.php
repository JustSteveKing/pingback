<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an organisation in the account', function (): void {
    $account = Account::factory()->create();

    $response = $this->withToken(ownerToken($account))
        ->postJson('/v1/organizations', ['name' => 'Rippin Group', 'country' => 'GB', 'city' => 'Leeds'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Rippin Group')
        ->assertJsonPath('data.country', 'GB')
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.deleted_at', null)
        ->assertETag();

    $organization = Organization::query()->findOrFail($response->json('data.id'));

    expect($organization->account_id)->toBe($account->id)
        ->and($response->headers->get('Location'))->toBe(route('v1.organizations.show', $organization));
});

it('gives the ETag a read would give', function (): void {
    $account = Account::factory()->create();
    $token = ownerToken($account);

    $created = $this->withToken($token)->postJson('/v1/organizations', ['name' => 'Rippin Group'])->assertCreated();

    expect($created->headers->get('ETag'))
        ->toBe(etagOf(Organization::query()->findOrFail($created->json('data.id')), $token));
});

it('replays a retry with the same Idempotency-Key rather than creating twice', function (): void {
    $account = Account::factory()->create();
    $token = ownerToken($account);

    $original = $this->withToken($token)
        ->postJson('/v1/organizations', ['name' => 'Rippin Group'], ['Idempotency-Key' => '"organization-1"'])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('/v1/organizations', ['name' => 'Rippin Group'], ['Idempotency-Key' => '"organization-1"'])
        ->assertIdempotentReplay($original);

    expect(Organization::query()->count())->toBe(1);
});

it('refuses an organisation with no name or a malformed country', function (): void {
    $account = Account::factory()->create();

    $this->withToken(ownerToken($account))
        ->postJson('/v1/organizations', ['country' => 'gb'])
        ->assertProblem(422)
        ->assertJsonValidationErrors(['name', 'country'])
        ->assertJsonPath('errors.country.0', __('api.organizations.country_format'));
});

it('requires authentication', function (): void {
    $this->postJson('/v1/organizations', [])
        ->assertProblem(401);
});

it('requires the organizations:write ability', function (): void {
    $account = Account::factory()->create();

    $this->withToken(ownerToken($account, ['organizations:read']))
        ->postJson('/v1/organizations', ['name' => 'Rippin Group'])
        ->assertProblem(403);
});
