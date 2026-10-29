<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists the account\'s organisations by name', function (): void {
    $account = Account::factory()->create();
    Organization::factory()->for($account)->create(['name' => 'Zeta']);
    Organization::factory()->for($account)->create(['name' => 'Alpha']);
    Organization::factory()->create(['name' => 'Another account\'s']);

    $this->withToken(ownerToken($account))
        ->getJson('/v1/organizations')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.name', 'Alpha')
        ->assertJsonPath('1.name', 'Zeta');
});

it('pages with a Link header that keeps the search', function (): void {
    $account = Account::factory()->create();
    Organization::factory()->count(30)->for($account)->sequence(fn($sequence): array => ['name' => sprintf('Acme %02d', $sequence->index)])->create();
    $token = ownerToken($account);

    $first = $this->withToken($token)->getJson('/v1/organizations?search=acme')
        ->assertOk()
        ->assertJsonCount(25)
        ->assertLinkHeader('next');

    preg_match('/<([^>]+)>;\s*rel="next"/', (string) $first->headers->get('Link'), $match);
    expect($match[1])->toContain('search=acme');

    $this->withToken($token)->getJson($match[1])
        ->assertOk()
        ->assertJsonCount(5)
        ->assertJsonPath('0.name', 'Acme 25')
        ->assertNoLinkHeader('next');
});

it('searches name and email, ignoring case', function (): void {
    $account = Account::factory()->create();
    Organization::factory()->for($account)->create(['name' => 'Rippin Group', 'email' => 'hello@rippin.example']);
    Organization::factory()->for($account)->create(['name' => 'Bins Ltd', 'email' => 'sales@acme.example']);
    Organization::factory()->for($account)->create(['name' => 'Kovacek', 'email' => 'kovacek@kovacek.example']);
    $token = ownerToken($account);

    $this->withToken($token)->getJson('/v1/organizations?search=RIPPIN')
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Rippin Group');

    $this->withToken($token)->getJson('/v1/organizations?search=acme')
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Bins Ltd');
});

it('treats % and _ in a search as the characters themselves', function (): void {
    $account = Account::factory()->create();
    Organization::factory()->for($account)->create(['name' => '100% Cotton', 'email' => null]);
    Organization::factory()->for($account)->create(['name' => '1000 Cotton', 'email' => null]);
    $token = ownerToken($account);

    $this->withToken($token)->getJson('/v1/organizations?search=' . urlencode('0%'))
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', '100% Cotton');

    $this->withToken($token)->getJson('/v1/organizations?search=' . urlencode('10_0'))
        ->assertJsonCount(0);
});

it('leaves deleted organisations out unless asked', function (): void {
    $account = Account::factory()->create();
    Organization::factory()->for($account)->create(['name' => 'Kept']);
    Organization::factory()->for($account)->create(['name' => 'Gone'])->delete();
    $token = ownerToken($account);

    $this->withToken($token)->getJson('/v1/organizations')
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Kept');

    $this->withToken($token)->getJson('/v1/organizations?trashed=with')
        ->assertJsonCount(2);

    $this->withToken($token)->getJson('/v1/organizations?trashed=only')
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Gone');
});

it('refuses an unknown trashed filter', function (): void {
    $account = Account::factory()->create();

    $this->withToken(ownerToken($account))
        ->getJson('/v1/organizations?trashed=all')
        ->assertProblem(422)
        ->assertJsonValidationErrors('trashed');
});

it('requires authentication', function (): void {
    $this->getJson('/v1/organizations')
        ->assertProblem(401);
});

it('requires the organizations:read ability', function (): void {
    $token = User::factory()->create()->createToken('test', ['auth:me'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/v1/organizations')
        ->assertProblem(403);
});
