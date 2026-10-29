<?php

declare(strict_types=1);

use JustSteveKing\Pingback\Data\Organization;
use JustSteveKing\Pingback\Inputs\NewOrganization;
use JustSteveKing\SdkToolbox\Errors\InvalidSignatureException;

/*
 * Both ends of the wire, for webhooks: Kit signs a real delivery with RFC 9421 and Content-Digest,
 * and the SDK's receiver verifies it with the subscription's secret and reads it back.
 */

it('verifies a real delivery, and reads the organisation it is about', function (): void {
    $delivery = delivery('webhooks/organization-created', 'organization.created', fn (): Organization => pingback('webhooks/organization-created')->organizations()->create(new NewOrganization('Contract Webhook Ltd')));

    $event = receiverFor($delivery)->receive($delivery['request']);

    expect($event->type)->toBe('organization.created')
        ->and($event->subject)->toBe($delivery['result']->id)
        ->and($event->organization()->name)->toBe('Contract Webhook Ltd')
        ->and($event->organization()->id)->toBe($delivery['result']->id);
});

it('refuses the same delivery with its body changed', function (): void {
    $delivery = delivery('webhooks/organization-created', 'organization.created', fn (): Organization => pingback('webhooks/organization-created')->organizations()->create(new NewOrganization('Contract Webhook Ltd')));
    $body = str_replace('Contract Webhook Ltd', 'Contract Webhook PLC', (string) $delivery['request']->getBody());
    $tampered = $delivery['request']->withBody(Http\Discovery\Psr17FactoryDiscovery::findStreamFactory()->createStream($body));

    expect(fn () => receiverFor($delivery)->receive($tampered))->toThrow(InvalidSignatureException::class);
});

it('refuses a delivery signed for another subscription', function (): void {
    $delivery = delivery('webhooks/organization-created', 'organization.created', fn (): Organization => pingback('webhooks/organization-created')->organizations()->create(new NewOrganization('Contract Webhook Ltd')));

    expect(fn () => receiverFor($delivery, subscription: '01jbd3z9y8x7w6v5t4s3r2q1p0')->receive($delivery['request']))
        ->toThrow(InvalidSignatureException::class);
});
