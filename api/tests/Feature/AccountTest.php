<?php

declare(strict_types=1);

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('creates an account the new user owns when they register (ADR-0003)', function (): void {
    Notification::fake();

    $this->postJson('/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'device_name' => 'test-suite',
    ])->assertCreated();

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
    $account = Account::query()->where('owner_id', $user->id)->firstOrFail();

    expect($account->name)->toBe('Jane Doe');
});

it('returns the account the token works in', function (): void {
    $account = Account::factory()->create(['name' => 'Acme Corporation']);
    Sanctum::actingAs($account->owner, ['account:read']);

    $this->getJson('/v1/account')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('data.id', $account->id)
        ->assertJsonPath('data.name', 'Acme Corporation')
        ->assertHeader('ETag');
});

it('answers a matching If-None-Match with 304', function (): void {
    $account = Account::factory()->create();
    Sanctum::actingAs($account->owner, ['account:read']);

    $etag = (string) $this->getJson('/v1/account')->headers->get('ETag');

    $this->getJson('/v1/account', ['If-None-Match' => $etag])->assertStatus(304);
});

it('refuses a token without account:read', function (): void {
    $account = Account::factory()->create();
    Sanctum::actingAs($account->owner, ['auth:me']);

    $this->getJson('/v1/account')
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('refuses a request with no token', function (): void {
    $this->getJson('/v1/account')
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json');
});
