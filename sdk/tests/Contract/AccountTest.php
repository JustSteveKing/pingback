<?php

declare(strict_types=1);

use JustSteveKing\SdkToolbox\Errors\ForbiddenException;
use JustSteveKing\SdkToolbox\Errors\UnauthorizedException;
use JustSteveKing\SdkToolbox\Testing\ArrayCache;

/*
 * Both ends of the wire, for the account. Every expectation here is something the API's own
 * tests check from the other side.
 */

it('gets the seeded account', function (): void {
    $account = pingback('account/get')->account();

    expect($account->name)->toBe('Acme Corporation')
        ->and($account->id)->toMatch('/^[0-9a-hjkmnp-tv-z]{26}$/');
});

it('revalidates a cached account with If-None-Match, and the API answers 304', function (): void {
    $log = new StatusLog;
    $sdk = pingback('account/revalidate', cache: new ArrayCache, events: $log);

    $first = $sdk->account();
    $second = $sdk->account();

    expect($second)->toEqual($first)
        ->and($log->statuses)->toBe([200, 304]);
});

it('turns a rejected token into UnauthorizedException, with the API\'s problem type', function (): void {
    try {
        pingback('account/unauthorized', token: 'not-a-real-token')->account();
        $this->fail('Expected UnauthorizedException');
    } catch (UnauthorizedException $e) {
        expect($e->problem?->type)->toBe('https://apiguide.dev/errors/unauthorized');
    }
});

it('turns a key without account:read into ForbiddenException', function (): void {
    try {
        pingback('account/forbidden', token: apiKey(['auth:me']))->account();
        $this->fail('Expected ForbiddenException');
    } catch (ForbiddenException $e) {
        expect($e->problem?->status)->toBe(403);
    }
});
