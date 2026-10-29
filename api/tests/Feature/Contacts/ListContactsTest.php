<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists the account\'s contacts by last name, then first name', function (): void {
    $account = Account::factory()->create();
    Contact::factory()->for($account)->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
    Contact::factory()->for($account)->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    Contact::factory()->for($account)->create(['first_name' => 'Adam', 'last_name' => 'Hopper']);
    Contact::factory()->create(['last_name' => 'Another account\'s']);

    $this->withToken(contactToken($account))
        ->getJson('/v1/contacts')
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonPath('0.first_name', 'Adam')
        ->assertJsonPath('1.first_name', 'Grace')
        ->assertJsonPath('2.first_name', 'Ada');
});

it('pages with a Link header', function (): void {
    $account = Account::factory()->create();
    Contact::factory()->count(30)->for($account)->create();
    $token = contactToken($account);

    $first = $this->withToken($token)->getJson('/v1/contacts')->assertJsonCount(25)->assertLinkHeader('next');
    preg_match('/<([^>]+)>;\s*rel="next"/', (string) $first->headers->get('Link'), $match);

    $this->withToken($token)->getJson($match[1])->assertJsonCount(5)->assertNoLinkHeader('next');
});

it('searches first name, last name, email and organisation name', function (string $search, string $expected): void {
    $account = Account::factory()->create();
    $rippin = Organization::factory()->for($account)->create(['name' => 'Rippin Group']);
    Contact::factory()->for($account)->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@engines.example']);
    Contact::factory()->for($account)->create(['first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@navy.example', 'organization_id' => $rippin->id]);

    $this->withToken(contactToken($account))
        ->getJson('/v1/contacts?search=' . urlencode($search))
        ->assertJsonCount(1)
        ->assertJsonPath('0.first_name', $expected);
})->with([
    'first name' => ['ADA', 'Ada'],
    'last name' => ['hopp', 'Grace'],
    'email' => ['engines', 'Ada'],
    'organisation name' => ['rippin', 'Grace'],
]);

it('lists the contacts at one organisation', function (): void {
    $account = Account::factory()->create();
    $organization = Organization::factory()->for($account)->create();
    Contact::factory()->for($account)->create(['first_name' => 'Ada', 'organization_id' => $organization->id]);
    Contact::factory()->for($account)->create(['first_name' => 'Grace']);

    $this->withToken(contactToken($account))
        ->getJson("/v1/contacts?organization_id={$organization->id}")
        ->assertJsonCount(1)
        ->assertJsonPath('0.first_name', 'Ada');
});

it('leaves deleted contacts out unless asked', function (): void {
    $account = Account::factory()->create();
    Contact::factory()->for($account)->create(['first_name' => 'Kept']);
    Contact::factory()->for($account)->create(['first_name' => 'Gone'])->delete();
    $token = contactToken($account);

    $this->withToken($token)->getJson('/v1/contacts')->assertJsonCount(1)->assertJsonPath('0.first_name', 'Kept');
    $this->withToken($token)->getJson('/v1/contacts?trashed=with')->assertJsonCount(2);
    $this->withToken($token)->getJson('/v1/contacts?trashed=only')->assertJsonCount(1)->assertJsonPath('0.first_name', 'Gone');
});

it('refuses a malformed filter', function (string $query, string $field): void {
    $this->withToken(contactToken(Account::factory()->create()))
        ->getJson("/v1/contacts?{$query}")
        ->assertProblem(422)
        ->assertJsonValidationErrors($field);
})->with([
    'trashed' => ['trashed=all', 'trashed'],
    'organisation' => ['organization_id=acme', 'organization_id'],
]);

it('requires authentication', function (): void {
    $this->getJson('/v1/contacts')
        ->assertProblem(401);
});

it('requires the contacts:read ability', function (): void {
    $token = User::factory()->create()->createToken('test', ['auth:me'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/v1/contacts')
        ->assertProblem(403);
});

it('deprecates filtering by organisation, with a sunset and a link to why (ADR-0008)', function (): void {
    $account = Account::factory()->create();
    $organization = Organization::factory()->for($account)->create();

    $this->withToken(contactToken($account))
        ->getJson("/v1/contacts?organization_id={$organization->id}")
        ->assertOk()
        ->assertDeprecated('2026-10-29T00:00:00Z', 'https://github.com/JustSteveKing/pingback/blob/main/architecture/adr/0008-the-organisation-filter-becomes-a-resource-and-is-deprecated.md')
        ->assertSunset('2027-01-31T23:59:59Z');
});

it('leaves the contact list itself undeprecated', function (): void {
    $this->withToken(contactToken(Account::factory()->create()))
        ->getJson('/v1/contacts?search=ada')
        ->assertOk()
        ->assertNotDeprecated();
});

it('retires the organisation filter with 410 after its sunset, and only that', function (): void {
    $account = Account::factory()->create();
    $organization = Organization::factory()->for($account)->create();
    $token = contactToken($account);

    $this->travelTo(new DateTimeImmutable('2027-02-01T00:00:00Z'));

    $this->withToken($token)->getJson("/v1/contacts?organization_id={$organization->id}")->assertProblem(410);
    $this->withToken($token)->getJson('/v1/contacts')->assertOk();
    $this->withToken($token)->getJson("/v1/organizations/{$organization->id}/contacts")->assertOk();
});
