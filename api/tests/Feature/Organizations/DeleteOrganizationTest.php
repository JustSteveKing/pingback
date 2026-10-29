<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deletes an organisation, keeping it to restore', function (): void {
    $organization = Organization::factory()->create();
    $token = ownerToken($organization->account);
    $etag = etagOf($organization, $token);

    $this->withToken($token)
        ->deleteJson("/v1/organizations/{$organization->id}", [], ['If-Match' => $etag])
        ->assertNoContent();

    expect($organization->refresh()->trashed())->toBeTrue()
        ->and(etagOf($organization, $token))->not->toBe($etag);
});

it('changes nothing when deleted again', function (): void {
    $organization = Organization::factory()->create();
    $organization->delete();
    $deletedAt = $organization->refresh()->deleted_at?->toAtomString();
    $token = ownerToken($organization->account);

    $this->travel(1)->minute();

    $this->withToken($token)
        ->deleteJson("/v1/organizations/{$organization->id}", [], ['If-Match' => etagOf($organization, $token)])
        ->assertNoContent();

    expect($organization->refresh()->deleted_at?->toAtomString())->toBe($deletedAt);
});

it('refuses a stale If-Match with 412', function (): void {
    $organization = Organization::factory()->create();
    $token = ownerToken($organization->account);
    $stale = etagOf($organization, $token);
    $organization->update(['name' => 'Changed by someone else']);

    $this->withToken($token)
        ->deleteJson("/v1/organizations/{$organization->id}", [], ['If-Match' => $stale])
        ->assertPreconditionFailed();

    expect($organization->refresh()->trashed())->toBeFalse();
});

it('requires If-Match, with 428', function (): void {
    $organization = Organization::factory()->create();

    $this->withToken(ownerToken($organization->account))
        ->deleteJson("/v1/organizations/{$organization->id}")
        ->assertPreconditionRequired();
});

it('treats another account\'s organisation as missing (ADR-0003)', function (): void {
    $organization = Organization::factory()->create();

    $this->withToken(ownerToken(Account::factory()->create()))
        ->deleteJson("/v1/organizations/{$organization->id}", [], ['If-Match' => '"anything"'])
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->deleteJson('/v1/organizations/01jbd0x4qsm2cf4c8m3x6y1v7z')
        ->assertProblem(401);
});

it('requires the organizations:write ability', function (): void {
    $organization = Organization::factory()->create();

    $this->withToken(ownerToken($organization->account, ['organizations:read']))
        ->deleteJson("/v1/organizations/{$organization->id}", [], ['If-Match' => '"anything"'])
        ->assertProblem(403);
});
