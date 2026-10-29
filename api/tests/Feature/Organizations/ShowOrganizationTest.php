<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows an organisation with its ETag', function (): void {
    $organization = Organization::factory()->create(['name' => 'Rippin Group']);

    $this->withToken(ownerToken($organization->account))
        ->getJson("/v1/organizations/{$organization->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $organization->id)
        ->assertJsonPath('data.name', 'Rippin Group')
        ->assertETag();
});

it('answers a matching If-None-Match with 304', function (): void {
    $organization = Organization::factory()->create();
    $token = ownerToken($organization->account);

    $this->withToken($token)
        ->getJson("/v1/organizations/{$organization->id}", ['If-None-Match' => etagOf($organization, $token)])
        ->assertNotModified();
});

it('shows a deleted organisation, marked deleted', function (): void {
    $organization = Organization::factory()->create();
    $organization->delete();

    $this->withToken(ownerToken($organization->account))
        ->getJson("/v1/organizations/{$organization->id}")
        ->assertOk()
        ->assertJsonPath('data.deleted_at', $organization->deleted_at?->toAtomString());
});

it('treats another account\'s organisation as missing (ADR-0003)', function (): void {
    $organization = Organization::factory()->create();

    $this->withToken(ownerToken(Account::factory()->create()))
        ->getJson("/v1/organizations/{$organization->id}")
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->getJson('/v1/organizations/01jbd0x4qsm2cf4c8m3x6y1v7z')
        ->assertProblem(401);
});

it('requires the organizations:read ability', function (): void {
    $token = User::factory()->create()->createToken('test', ['auth:me'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/v1/organizations/01jbd0x4qsm2cf4c8m3x6y1v7z')
        ->assertProblem(403);
});
