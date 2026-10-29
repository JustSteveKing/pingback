<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists the contacts at one organisation, by last name then first name, and is not deprecated', function (): void {
    $account = Account::factory()->create();
    $organization = Organization::factory()->for($account)->create();
    Contact::factory()->for($account)->create(['first_name' => 'Grace', 'last_name' => 'Hopper', 'organization_id' => $organization->id]);
    Contact::factory()->for($account)->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'organization_id' => $organization->id]);
    Contact::factory()->for($account)->create(['first_name' => 'Elsewhere']);

    $this->withToken(contactToken($account))
        ->getJson("/v1/organizations/{$organization->id}/contacts")
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.first_name', 'Grace')
        ->assertJsonPath('1.first_name', 'Ada')
        ->assertNotDeprecated();
});

it('pages with a Link header, and searches and filters as the contact list does', function (): void {
    $account = Account::factory()->create();
    $organization = Organization::factory()->for($account)->create();
    Contact::factory()->count(30)->for($account)->create(['organization_id' => $organization->id]);
    Contact::factory()->for($account)->create(['first_name' => 'Gone', 'organization_id' => $organization->id])->delete();
    $token = contactToken($account);

    $this->withToken($token)->getJson("/v1/organizations/{$organization->id}/contacts")->assertJsonCount(25)->assertLinkHeader('next');
    $this->withToken($token)->getJson("/v1/organizations/{$organization->id}/contacts?trashed=only")->assertJsonCount(1)->assertJsonPath('0.first_name', 'Gone');
    $this->withToken($token)->getJson("/v1/organizations/{$organization->id}/contacts?search=gone&trashed=with")->assertJsonCount(1);
});

it('still lists the contacts at a deleted organisation', function (): void {
    $organization = Organization::factory()->create();
    Contact::factory()->for($organization->account)->create(['organization_id' => $organization->id]);
    $organization->delete();

    $this->withToken(contactToken($organization->account))
        ->getJson("/v1/organizations/{$organization->id}/contacts")
        ->assertOk()
        ->assertJsonCount(1);
});

it('treats another account\'s organisation as missing (ADR-0003)', function (): void {
    $organization = Organization::factory()->create();

    $this->withToken(contactToken(Account::factory()->create()))
        ->getJson("/v1/organizations/{$organization->id}/contacts")
        ->assertProblem(404);
});

it('requires authentication', function (): void {
    $this->getJson('/v1/organizations/01jbd0x4qsm2cf4c8m3x6y1v7z/contacts')
        ->assertProblem(401);
});

it('requires the contacts:read ability', function (): void {
    $token = User::factory()->create()->createToken('test', ['organizations:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/v1/organizations/01jbd0x4qsm2cf4c8m3x6y1v7z/contacts')
        ->assertProblem(403);
});
