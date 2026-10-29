<?php

declare(strict_types=1);

use JustSteveKing\Pingback\Data\Contact;
use JustSteveKing\Pingback\Data\Organization;
use JustSteveKing\Pingback\Data\Phone;
use JustSteveKing\Pingback\Inputs\NewContact;
use JustSteveKing\SdkToolbox\Testing\FakeClient;
use JustSteveKing\SdkToolbox\Testing\FakeResponse;

/**
 * @return array<string, string|null>
 */
function contactJson(array $overrides = []): array
{
    return [
        'id' => '01m63qf6c1yvphnafgx4qc8n2j',
        'organization_id' => '01m63p2z7qwssrw1x1p7fpxgvm',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@engines.example',
        'phone' => '+44 20 7946 0000',
        'phones' => [['number' => '+44 20 7946 0000', 'label' => 'work']],
        'address' => null,
        'city' => 'London',
        'region' => null,
        'country' => 'GB',
        'postal_code' => null,
        'created_at' => '2026-10-29T11:17:55+00:00',
        'updated_at' => '2026-10-29T11:17:55+00:00',
        'deleted_at' => null,
        ...$overrides,
    ];
}

it('reads a contact with its organisation and ETag', function (): void {
    $http = new FakeClient(FakeResponse::json(['data' => contactJson()], headers: ['ETag' => '"v1"']));

    $contact = sdk($http)->contacts()->get('01m63qf6c1yvphnafgx4qc8n2j');

    expect($contact->name())->toBe('Ada Lovelace')
        ->and($contact->organizationId)->toBe('01m63p2z7qwssrw1x1p7fpxgvm')
        ->and($contact->etag)->toBe('"v1"');
});

it('takes a contact away from its organisation by patching it to null', function (): void {
    $http = new FakeClient(
        FakeResponse::json(['data' => contactJson()], headers: ['ETag' => '"v1"']),
        FakeResponse::json(['data' => contactJson(['organization_id' => null])], headers: ['ETag' => '"v2"']),
    );
    $contacts = sdk($http)->contacts();

    $contact = $contacts->get('01m63qf6c1yvphnafgx4qc8n2j');
    $contacts->update($contact, $contact->with(organizationId: null));

    expect(json_decode((string) $http->last()?->getBody(), true))->toBe(['organization_id' => null])
        ->and($http->last()?->getHeaderLine('If-Match'))->toBe('"v1"');
});

it('lists the contacts at an organisation', function (): void {
    $http = new FakeClient(FakeResponse::json([contactJson()]));
    $organization = new Organization('01m63p2z7qwssrw1x1p7fpxgvm', 'Rippin Group', null, null, null, null, null, null, null, null, null, null);

    sdk($http)->contacts()->all(search: 'ada', at: $organization)->all();

    // The organisation's own resource, never the deprecated organization_id filter (ADR-0008).
    expect($http->last()?->getUri()->getPath())->toBe('/v1/organizations/01m63p2z7qwssrw1x1p7fpxgvm/contacts')
        ->and($http->last()?->getUri()->getQuery())->toBe('search=ada');
});

it('creates with the API\'s field names', function (): void {
    $http = new FakeClient(FakeResponse::json(['data' => contactJson()], 201));

    sdk($http)->contacts()->create(new NewContact('Ada', 'Lovelace', organizationId: '01m63p2z7qwssrw1x1p7fpxgvm'));

    expect(json_decode((string) $http->last()?->getBody(), true))->toBe(['organization_id' => '01m63p2z7qwssrw1x1p7fpxgvm', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'phones' => []]);
});

it('never clears a name', function (string $field): void {
    $contact = new Contact('01m63qf6c1yvphnafgx4qc8n2j', null, 'Ada', 'Lovelace', null, null, [], null, null, null, null, null, null, null, null);

    expect(fn () => $contact->with(...[$field => null]))->toThrow(InvalidArgumentException::class);
})->with(['firstName', 'lastName']);

it('reads the list of phones, and the deprecated phone beside it', function (): void {
    $http = new FakeClient(FakeResponse::json(['data' => contactJson()], headers: ['ETag' => '"v1"']));

    $contact = sdk($http)->contacts()->get('01m63qf6c1yvphnafgx4qc8n2j');

    expect($contact->phones)->toEqual([new Phone('+44 20 7946 0000', 'work')]);
});

it('sends the whole list of phones when they change, as merge patch replaces arrays', function (): void {
    $http = new FakeClient(
        FakeResponse::json(['data' => contactJson()], headers: ['ETag' => '"v1"']),
        FakeResponse::json(['data' => contactJson()], headers: ['ETag' => '"v2"']),
    );
    $contacts = sdk($http)->contacts();

    $contact = $contacts->get('01m63qf6c1yvphnafgx4qc8n2j');
    $contacts->update($contact, $contact->withPhones(new Phone('+44 20 7946 0000', 'work'), new Phone('+44 7700 900000', 'mobile')));

    expect(json_decode((string) $http->last()?->getBody(), true))->toBe(['phones' => [
        ['number' => '+44 20 7946 0000', 'label' => 'work'],
        ['number' => '+44 7700 900000', 'label' => 'mobile'],
    ]]);
});

it('turns the deprecated with(phone:) into a list of one, and says it is deprecated', function (): void {
    $contact = new Contact('01m63qf6c1yvphnafgx4qc8n2j', null, 'Ada', 'Lovelace', null, null, [], null, null, null, null, null, null, null, null);
    $deprecations = [];
    set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
        $deprecations[] = $message;

        return true;
    }, E_USER_DEPRECATED);

    try {
        $changed = $contact->with(phone: '+1 555 0100', city: 'York');
    } finally {
        restore_error_handler();
    }

    expect($changed->phones)->toEqual([new Phone('+1 555 0100')])
        ->and($changed->phone)->toBe('+1 555 0100')
        ->and($changed->city)->toBe('York')
        ->and($deprecations)->toHaveCount(1);
});
