<?php

declare(strict_types=1);

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('limits each user to the configured requests a minute', function (): void {
    config(['security.rate_limit' => 2]);
    $account = Account::factory()->create();
    Sanctum::actingAs($account->owner, ['account:read']);

    $this->getJson('/v1/account')->assertOk();
    $this->getJson('/v1/account')->assertOk();
    $this->getJson('/v1/account')->assertProblem(429)->assertHeader('Retry-After');
});
