<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('changes only the fields in the patch, and gives the new ETag', function (): void {
    $organization = Organization::factory()->create(['name' => 'Rippin Group', 'city' => 'Leeds']);
    $token = ownerToken($organization->account);
    $etag = etagOf($organization, $token);

    $response = mergePatch("/v1/organizations/{$organization->id}", ['name' => 'Rippin and Sons'], $token, ['If-Match' => $etag])
        ->assertOk()
        ->assertJsonPath('data.name', 'Rippin and Sons')
        ->assertJsonPath('data.city', 'Leeds')
        ->assertETag();

    expect($response->headers->get('ETag'))
        ->not->toBe($etag)
        ->toBe(etagOf($organization->refresh(), $token));
});

it('clears a nullable field patched to null', function (): void {
    $organization = Organization::factory()->create(['email' => 'hello@rippin.example']);
    $token = ownerToken($organization->account);

    mergePatch("/v1/organizations/{$organization->id}", ['email' => null], $token, ['If-Match' => etagOf($organization, $token)])
        ->assertOk()
        ->assertJsonPath('data.email', null);
});

it('refuses to clear the name', function (): void {
    $organization = Organization::factory()->create();
    $token = ownerToken($organization->account);

    mergePatch("/v1/organizations/{$organization->id}", ['name' => null], $token, ['If-Match' => etagOf($organization, $token)])
        ->assertProblem(422)
        ->assertJsonValidationErrors('name');
});

it('refuses a stale If-Match with 412, leaving the record alone', function (): void {
    $organization = Organization::factory()->create(['name' => 'Rippin Group']);
    $token = ownerToken($organization->account);
    $stale = etagOf($organization, $token);

    mergePatch("/v1/organizations/{$organization->id}", ['name' => 'First'], $token, ['If-Match' => $stale])->assertOk();

    mergePatch("/v1/organizations/{$organization->id}", ['name' => 'Second'], $token, ['If-Match' => $stale])
        ->assertPreconditionFailed();

    expect($organization->refresh()->name)->toBe('First');
});

it('requires If-Match, with 428', function (): void {
    $organization = Organization::factory()->create();

    mergePatch("/v1/organizations/{$organization->id}", ['name' => 'Rippin and Sons'], ownerToken($organization->account))
        ->assertPreconditionRequired();
});

it('accepts only a merge patch', function (): void {
    $organization = Organization::factory()->create();
    $token = ownerToken($organization->account);

    $this->withToken($token)
        ->patchJson("/v1/organizations/{$organization->id}", ['name' => 'Rippin and Sons'], ['If-Match' => etagOf($organization, $token)])
        ->assertProblem(415);
});

it('treats another account\'s organisation as missing (ADR-0003)', function (): void {
    $organization = Organization::factory()->create();

    mergePatch("/v1/organizations/{$organization->id}", ['name' => 'Mine now'], ownerToken(Account::factory()->create()), ['If-Match' => '"anything"'])
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->patchJson('/v1/organizations/01jbd0x4qsm2cf4c8m3x6y1v7z', [])
        ->assertProblem(401);
});

it('requires the organizations:write ability', function (): void {
    $organization = Organization::factory()->create();

    mergePatch("/v1/organizations/{$organization->id}", ['name' => 'Rippin and Sons'], ownerToken($organization->account, ['organizations:read']), ['If-Match' => '"anything"'])
        ->assertProblem(403);
});
