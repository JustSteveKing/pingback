<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback;

use JustSteveKing\Pingback\Data\Account;
use JustSteveKing\Pingback\Requests\GetAccount;
use JustSteveKing\Pingback\Resources\Organizations;
use JustSteveKing\SdkToolbox\Auth\Authenticator;
use JustSteveKing\SdkToolbox\Auth\BearerToken;
use JustSteveKing\SdkToolbox\Connector;
use JustSteveKing\SdkToolbox\Hydration\Hydrator;
use JustSteveKing\SdkToolbox\Hydration\ValinorHydrator;
use JustSteveKing\SdkToolbox\Retries\RetryPolicy;
use JustSteveKing\SdkToolbox\Retries\RetryStrategy;
use JustSteveKing\SdkToolbox\Time\Sleeper;
use JustSteveKing\SdkToolbox\Time\SystemClock;
use JustSteveKing\SdkToolbox\Time\SystemSleeper;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use SensitiveParameter;

/**
 * Pingback's API, as a PHP SDK on sdk-toolbox: the client end of the wire, built to the same
 * standards as the API it calls.
 *
 *     $pingback = new Pingback(getenv('PINGBACK_TOKEN'), 'http://127.0.0.1:8000/v1');
 *
 *     echo $pingback->account()->name;
 *
 * The token is a session token or an API key from the API. Pingback runs wherever you run it, so
 * there is no default base URI. Credentials only go over plain HTTP to a loopback address.
 */
final class Pingback extends Connector
{
    public const string VERSION = '0.0.1-dev';

    public function __construct(
        #[SensitiveParameter] string|Authenticator $token,
        string $baseUri,
        ?ClientInterface $http = null,
        RetryStrategy $retry = new RetryPolicy,
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null,
        ?EventDispatcherInterface $events = null,
        ClockInterface $clock = new SystemClock,
        Sleeper $sleeper = new SystemSleeper,
        ?Hydrator $hydrator = null,
    ) {
        parent::__construct(
            baseUri: $baseUri,
            auth: is_string($token) ? new BearerToken($token) : $token,
            http: $http,
            userAgent: 'pingback-sdk-php/'.self::VERSION,
            insecure: self::isLoopback($baseUri),
            retry: $retry,
            clock: $clock,
            sleeper: $sleeper,
            hydrator: $hydrator ?? new ValinorHydrator,
            cache: $cache,
            logger: $logger,
            events: $events,
        );
    }

    /**
     * The account the token works in.
     */
    public function account(): Account
    {
        return $this->send(new GetAccount);
    }

    public function organizations(): Organizations
    {
        return new Organizations($this);
    }

    private static function isLoopback(string $baseUri): bool
    {
        return in_array(parse_url($baseUri, PHP_URL_HOST), ['localhost', '127.0.0.1', '[::1]'], true);
    }
}
