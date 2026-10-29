<?php

declare(strict_types=1);

use JustSteveKing\Pingback\Pingback;
use JustSteveKing\SdkToolbox\Errors\InvalidRequestException;
use JustSteveKing\SdkToolbox\Testing\FakeClient;
use JustSteveKing\SdkToolbox\Testing\FakeResponse;

function account(): array
{
    return ['data' => ['id' => '01m63p2z7qwssrw1x1p7fpxgvm', 'name' => 'Acme Corporation', 'created_at' => '2026-10-29T11:17:55+00:00', 'updated_at' => '2026-10-29T11:17:55+00:00']];
}

it('sends the token as a bearer token and says who it is', function (): void {
    $http = new FakeClient(FakeResponse::json(account()));

    (new Pingback('a-token', 'http://127.0.0.1:8000/v1', $http))->account();

    expect($http->last()?->getHeaderLine('Authorization'))->toBe('Bearer a-token')
        ->and($http->last()?->getHeaderLine('User-Agent'))->toStartWith('pingback-sdk-php/'.Pingback::VERSION.' sdk-toolbox/');
    $http->assertSentTo('GET', '/v1/account');
});

it('allows plain HTTP only to a loopback address', function (string $base): void {
    expect(fn () => new Pingback('a-token', $base, new FakeClient))->not->toThrow(InvalidRequestException::class);
})->with(['127.0.0.1' => 'http://127.0.0.1:8000/v1', 'localhost' => 'http://localhost/v1', 'https anywhere' => 'https://pingback.example.com/v1']);

it('refuses to send a token over plain HTTP to anywhere else', function (): void {
    expect(fn () => new Pingback('a-token', 'http://pingback.example.com/v1', new FakeClient))->toThrow(InvalidRequestException::class);
});

it('keeps the token out of a dump of the SDK', function (): void {
    expect(print_r(new Pingback('top-secret-token', 'https://pingback.example.com/v1', new FakeClient), true))->not->toContain('top-secret-token');
});
