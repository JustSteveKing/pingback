<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Webhooks;

use JustSteveKing\SdkToolbox\Errors\InvalidSignatureException;
use JustSteveKing\SdkToolbox\Hydration\Hydrator;
use JustSteveKing\SdkToolbox\Hydration\ValinorHydrator;
use JustSteveKing\SdkToolbox\Signatures\MessageVerifier;
use JustSteveKing\SdkToolbox\Signatures\VerificationKey;
use JustSteveKing\SdkToolbox\Time\SystemClock;
use JustSteveKing\SdkToolbox\Webhooks\SignedWebhook;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\RequestInterface;
use SensitiveParameter;

/**
 * Receives Pingback's webhooks: checks each delivery's RFC 9421 signature, made with the
 * subscription's secret, and the RFC 9530 Content-Digest of its body, then reads the CloudEvent.
 *
 *     $receiver = new WebhookReceiver($subscriptionId, getenv('PINGBACK_WEBHOOK_SECRET'));
 *     $event = $receiver->receive($request);   // a PSR-7 request, before anything reads the body
 *
 * The signature covers the method and the URI the delivery was sent to, so give it the request as
 * Pingback addressed it: behind a proxy, rebuild the URI from the forwarded host and scheme. A
 * signature older than five minutes is refused, so a captured delivery can't be replayed later.
 */
final readonly class WebhookReceiver
{
    /**
     * What every Pingback signature covers. A signature covering less is refused.
     */
    private const array COVERED = ['@method', '@target-uri', 'content-type', 'content-digest'];

    private SignedWebhook $verifier;

    public function __construct(
        private string $subscriptionId,
        #[SensitiveParameter] string $secret,
        ClockInterface $clock = new SystemClock,
        private Hydrator $hydrator = new ValinorHydrator,
    ) {
        $key = VerificationKey::hmac($subscriptionId, $secret);

        $this->verifier = new SignedWebhook(new MessageVerifier(
            keys: fn (string $keyId): ?VerificationKey => $keyId === $this->subscriptionId ? $key : null,
            required: self::COVERED,
            clock: $clock,
        ));
    }

    /**
     * @throws InvalidSignatureException when the delivery isn't from this subscription, or was changed
     */
    public function receive(RequestInterface $request): Event
    {
        $this->verifier->verify($request);

        $request->getBody()->rewind();
        $event = json_decode((string) $request->getBody(), true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null) || ! is_string($event['source'] ?? null) || ! is_string($event['time'] ?? null)) {
            throw new InvalidSignatureException('The delivery is signed, but it is not a CloudEvent.');
        }

        $data = is_array($event['data'] ?? null) ? $event['data'] : [];

        /** @var array<string, mixed> $data */
        return new Event(
            id: $event['id'],
            type: $event['type'],
            source: $event['source'],
            subject: is_string($event['subject'] ?? null) ? $event['subject'] : null,
            time: $event['time'],
            data: $data,
            hydrator: $this->hydrator,
        );
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['subscriptionId' => $this->subscriptionId];
    }
}
