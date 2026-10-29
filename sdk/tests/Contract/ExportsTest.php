<?php

declare(strict_types=1);

use JustSteveKing\Pingback\Inputs\NewContact;
use JustSteveKing\Pingback\Inputs\NewOrganization;
use JustSteveKing\SdkToolbox\Errors\ForbiddenException;

/*
 * Both ends of the wire, for the asynchronous export (ADR-0005): api-toolbox's 202 and monitor on
 * one side, sdk-toolbox's await() on the other.
 */

it('exports the account\'s contacts, waiting on the monitor for the file', function (): void {
    $pingback = pingback('exports/create');
    $organization = $pingback->organizations()->create(new NewOrganization('Contract Export Engines'));
    $contact = $pingback->contacts()->create(new NewContact('Ada', 'Exported', organizationId: $organization->id));

    $export = $pingback->exports()->create();
    $rows = array_column($export->rows(), null, 'id');

    expect(array_keys($export->rows()[0]))->toBe(['id', 'first_name', 'last_name', 'email', 'phone', 'organization', 'address', 'city', 'region', 'country', 'postal_code'])
        ->and($rows[$contact->id]['last_name'])->toBe('Exported')
        ->and($rows[$contact->id]['organization'])->toBe('Contract Export Engines')
        ->and(count($rows))->toBeGreaterThan(100);
});

it('downloads the export an export.completed webhook announces', function (): void {
    $delivery = delivery('exports/webhook', 'export.completed', fn () => pingback('exports/webhook')->exports()->create());

    $event = receiverFor($delivery)->receive($delivery['request']);
    $announced = $event->export();
    $downloaded = pingback('exports/webhook-download')->exports()->download($announced);

    expect($event->type)->toBe('export.completed')
        ->and($announced->id)->toBe($delivery['result']->id)
        ->and($announced->rows)->toBe(count($delivery['result']->rows()))
        ->and($downloaded->csv)->toBe($delivery['result']->csv);
});

it('turns a key without exports:create into ForbiddenException', function (): void {
    expect(fn () => pingback('exports/forbidden', token: apiKey(['exports:read']))->exports()->create())
        ->toThrow(ForbiddenException::class);
});
