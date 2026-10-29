<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('restores a deleted organisation, with a new ETag', function (): void {
    $organization = Organization::factory()->create();
    $token = ownerToken($organization->account);
    $before = etagOf($organization, $token);
    $organization->delete();

    $response = $this->withToken($token)
        ->postJson("/v1/organizations/{$organization->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.deleted_at', null)
        ->assertETag();

    expect($organization->refresh()->trashed())->toBeFalse()
        ->and($response->headers->get('ETag'))->not->toBe($before);
});

it('changes nothing for an organisation that is not deleted', function (): void {
    $organization = Organization::factory()->create();
    $token = ownerToken($organization->account);
    $etag = etagOf($organization, $token);

    $this->withToken($token)
        ->postJson("/v1/organizations/{$organization->id}/restore")
        ->assertOk()
        ->assertETag($etag);
});

it('treats another account\'s organisation as missing (ADR-0003)', function (): void {
    $organization = Organization::factory()->create();
    $organization->delete();

    $this->withToken(ownerToken(Account::factory()->create()))
        ->postJson("/v1/organizations/{$organization->id}/restore")
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->postJson('/v1/organizations/01jbd0x4qsm2cf4c8m3x6y1v7z/restore')
        ->assertProblem(401);
});

it('requires the organizations:write ability', function (): void {
    $organization = Organization::factory()->create();

    $this->withToken(ownerToken($organization->account, ['organizations:read']))
        ->postJson("/v1/organizations/{$organization->id}/restore")
        ->assertProblem(403);
});
