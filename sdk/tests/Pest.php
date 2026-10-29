<?php

declare(strict_types=1);

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use JustSteveKing\Pingback\Pingback;
use JustSteveKing\Pingback\Webhooks\WebhookReceiver;
use JustSteveKing\SdkToolbox\Retries\RetryPolicy;
use JustSteveKing\SdkToolbox\Testing\FakeTime;
use JustSteveKing\SdkToolbox\Testing\Recorder;
use JustSteveKing\SdkToolbox\Testing\RecordMode;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
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
 * A session token for a newly registered user, so in an account of its own.
 */
function otherAccountToken(): string
{
    if (! live()) {
        return 'replayed-other-token';
    }

    return post('/auth/register', [
        'name' => 'Another Account',
        'email' => 'contract-'.bin2hex(random_bytes(8)).'@example.com',
        'password' => 'secret-another-account',
        'device_name' => 'pingback-sdk-contract',
    ]);
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

/**
 * A webhook delivery the API really sent, with what it takes to verify it. Live, it subscribes
 * a receiver on 127.0.0.1:8001 to the event type, runs the trigger, and waits for the delivery,
 * which needs the API's queue worker running and WEBHOOKS_REQUIRE_HTTPS=false and
 * WEBHOOKS_ALLOW_PRIVATE_NETWORKS=true. Recording saves it beside the trigger's fixture; a replay
 * runs the trigger against its fixture and reads the delivery back.
 *
 * @template T
 *
 * @param  Closure(): T  $trigger
 * @return array{result: T, request: RequestInterface, subscription: string, secret: string, received_at: string}
 */
function delivery(string $fixture, string $type, Closure $trigger): array
{
    // One delivery per fixture per run: the tests that look at it differently share it.
    static $made = [];

    return $made[$fixture] ??= makeDelivery($fixture, $type, $trigger);
}

/**
 * @template T
 *
 * @param  Closure(): T  $trigger
 * @return array{result: T, request: RequestInterface, subscription: string, secret: string, received_at: string}
 */
function makeDelivery(string $fixture, string $type, Closure $trigger): array
{
    $file = __DIR__."/Fixtures/{$fixture}-delivery.json";

    if (! live()) {
        $result = $trigger();
        /** @var array{subscription: string, secret: string, delivery: array{method: string, uri: string, headers: array<string, string>, body: string, received_at: string}} $saved */
        $saved = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        return ['result' => $result, 'request' => deliveredRequest($saved['delivery']), 'subscription' => $saved['subscription'], 'secret' => $saved['secret'], 'received_at' => $saved['delivery']['received_at']];
    }

    $inbox = sys_get_temp_dir().'/pingback-inbox-'.bin2hex(random_bytes(6));
    mkdir($inbox);
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:8001', __DIR__.'/Support/receiver.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes, null, ['WEBHOOK_INBOX' => $inbox, 'PATH' => (string) getenv('PATH')]);
    usleep(300_000);

    $subscription = postJson('/webhooks', ['url' => 'http://127.0.0.1:8001/hooks', 'events' => [$type]], demoToken());

    try {
        $result = $trigger();
        $deadline = microtime(true) + 20;

        do {
            usleep(250_000);
            $files = glob("{$inbox}/*.json") ?: [];
        } while ($files === [] && microtime(true) < $deadline);

        if ($files === []) {
            throw new RuntimeException("No {$type} delivery arrived. Is the API's queue worker running, with private webhook targets allowed?");
        }

        /** @var array{method: string, uri: string, headers: array<string, string>, body: string, received_at: string} $delivered */
        $delivered = json_decode((string) file_get_contents($files[0]), true, flags: JSON_THROW_ON_ERROR);
    } finally {
        sendJson('DELETE', '/webhooks/'.$subscription['data']['id'], null, demoToken());
        proc_terminate($server);
        array_map(unlink(...), glob("{$inbox}/*") ?: []);
        rmdir($inbox);
    }

    if (RecordMode::fromEnvironment() === RecordMode::Record) {
        // A throwaway subscription on a local API, deleted above: its secret signs nothing else.
        file_put_contents($file, json_encode(['subscription' => $subscription['data']['id'], 'secret' => $subscription['meta']['secret'], 'delivery' => $delivered], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    return ['result' => $result, 'request' => deliveredRequest($delivered), 'subscription' => $subscription['data']['id'], 'secret' => $subscription['meta']['secret'], 'received_at' => $delivered['received_at']];
}

/**
 * @param  array{method: string, uri: string, headers: array<string, string>, body: string}  $delivered
 */
function deliveredRequest(array $delivered): RequestInterface
{
    $request = Psr17FactoryDiscovery::findRequestFactory()->createRequest($delivered['method'], $delivered['uri'])
        ->withBody(Psr17FactoryDiscovery::findStreamFactory()->createStream($delivered['body']));

    foreach ($delivered['headers'] as $name => $value) {
        $request = $request->withHeader($name, $value);
    }

    return $request;
}

/**
 * A receiver for the delivery, with the clock at the moment it arrived, so a replay is as fresh
 * as the live run was.
 *
 * @param  array{subscription: string, secret: string, received_at: string}  $delivery
 */
function receiverFor(array $delivery, ?string $subscription = null): WebhookReceiver
{
    return new WebhookReceiver($subscription ?? $delivery['subscription'], $delivery['secret'], new FakeTime($delivery['received_at']));
}

/**
 * @param  array<string, mixed>  $body
 * @return array<string, mixed>
 */
function postJson(string $path, array $body, string $token): array
{
    return sendJson('POST', $path, $body, $token);
}

/**
 * @param  array<string, mixed>|null  $body
 * @return array<string, mixed>
 */
function sendJson(string $method, string $path, ?array $body, string $token): array
{
    $factory = Psr17FactoryDiscovery::findRequestFactory();
    $request = $factory->createRequest($method, baseUri().$path)
        ->withHeader('Accept', 'application/json')
        ->withHeader('Authorization', "Bearer {$token}");

    if ($body !== null) {
        $request = $request->withHeader('Content-Type', 'application/json')
            ->withBody(Psr17FactoryDiscovery::findStreamFactory()->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
    }

    $response = http()->sendRequest($request);

    if ($response->getStatusCode() >= 300) {
        throw new RuntimeException("{$method} {$path} answered {$response->getStatusCode()}: {$response->getBody()}");
    }

    /** @var array<string, mixed> */
    return json_decode((string) $response->getBody(), true) ?? [];
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
