<?php

declare(strict_types=1);

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use JustSteveKing\Pingback\Pingback;
use JustSteveKing\SdkToolbox\Retries\RetryPolicy;
use JustSteveKing\SdkToolbox\Testing\Recorder;
use JustSteveKing\SdkToolbox\Testing\RecordMode;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;

/*
 * The contract suite runs the SDK against the real API when PINGBACK_BASE_URI is set (ADR-0006),
 * and against the fixtures it recorded otherwise. SDK_FIXTURES=record re-records them.
 *
 *     PINGBACK_BASE_URI=http://127.0.0.1:8000/v1 SDK_FIXTURES=record vendor/bin/pest --testsuite=Contract
 */
const RECORDED_BASE_URI = 'http://127.0.0.1:8000/v1';

function live(): bool
{
    return is_string(getenv('PINGBACK_BASE_URI')) && getenv('PINGBACK_BASE_URI') !== '';
}

function baseUri(): string
{
    return live() ? (string) getenv('PINGBACK_BASE_URI') : RECORDED_BASE_URI;
}

/**
 * The SDK for one contract test: live, recording, or replaying the fixture of that name.
 */
function pingback(string $fixture, ?string $token = null, ?CacheInterface $cache = null, ?EventDispatcherInterface $events = null): Pingback
{
    $http = match (true) {
        ! live() => new Recorder(__DIR__.'/Fixtures', $fixture, RecordMode::Replay),
        RecordMode::fromEnvironment() === RecordMode::Record => new Recorder(__DIR__.'/Fixtures', $fixture, RecordMode::Record, http()),
        default => null,
    };

    return new Pingback($token ?? demoToken(), baseUri(), $http, retry: RetryPolicy::none(), cache: $cache, events: $events);
}

/**
 * A session token for the seeded demo user. Signing in is the API's business, not the SDK's, so
 * it goes over plain HTTP and is never recorded. Replays need no real token.
 */
function demoToken(): string
{
    static $token = null;

    if (! live()) {
        return 'replayed-token';
    }

    return $token ??= post('/auth/login', [
        'email' => 'johndoe@example.com',
        'password' => 'secret-pingback-demo',
        'device_name' => 'pingback-sdk-contract',
    ]);
}

/**
 * An API key holding only the given abilities, made with the demo token.
 *
 * @param  list<string>  $abilities
 */
function apiKey(array $abilities): string
{
    if (! live()) {
        return 'replayed-api-key';
    }

    return post('/auth/tokens', ['name' => 'contract-'.implode('-', $abilities), 'abilities' => $abilities], demoToken());
}

/**
 * @param  array<string, mixed>  $body
 */
function post(string $path, array $body, ?string $token = null): string
{
    $factory = Psr17FactoryDiscovery::findRequestFactory();
    $request = $factory->createRequest('POST', baseUri().$path)
        ->withHeader('Accept', 'application/json')
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Idempotency-Key', '"'.bin2hex(random_bytes(16)).'"')
        ->withBody(Psr17FactoryDiscovery::findStreamFactory()->createStream(json_encode($body, JSON_THROW_ON_ERROR)));

    if ($token !== null) {
        $request = $request->withHeader('Authorization', "Bearer {$token}");
    }

    $response = http()->sendRequest($request);
    /** @var array{meta?: array{token?: string}} $json */
    $json = json_decode((string) $response->getBody(), true);

    return $json['meta']['token'] ?? throw new RuntimeException("{$path} answered {$response->getStatusCode()}: {$response->getBody()}");
}

function http(): ClientInterface
{
    return Psr18ClientDiscovery::find();
}

/**
 * Records the status of every response the SDK receives, through sdk-toolbox's own events.
 */
final class StatusLog implements EventDispatcherInterface
{
    /** @var list<int> */
    public array $statuses = [];

    public function dispatch(object $event): object
    {
        if ($event instanceof JustSteveKing\SdkToolbox\Events\ResponseReceived) {
            $this->statuses[] = $event->response->getStatusCode();
        }

        return $event;
    }
}
