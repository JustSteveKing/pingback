<?php

declare(strict_types=1);

use JustSteveKing\Pingback\Data\Organization;
use JustSteveKing\Pingback\Inputs\NewOrganization;
use JustSteveKing\Pingback\Inputs\Trashed;
use JustSteveKing\Pingback\Pingback;
use JustSteveKing\SdkToolbox\Testing\FakeClient;
use JustSteveKing\SdkToolbox\Testing\FakeResponse;

/**
 * @return array<string, string|null>
 */
function organizationJson(array $overrides = []): array
{
    return [
        'id' => '01m63p2z7qwssrw1x1p7fpxgvm',
        'name' => 'Rippin Group',
        'email' => 'hello@rippin.example',
        'phone' => null,
        'address' => null,
        'city' => 'York',
        'region' => null,
        'country' => 'GB',
        'postal_code' => null,
        'created_at' => '2026-10-29T11:17:55+00:00',
        'updated_at' => '2026-10-29T11:17:55+00:00',
        'deleted_at' => null,
        ...$overrides,
    ];
}

function sdk(FakeClient $http): Pingback
{
    return new Pingback('a-token', 'http://127.0.0.1:8000/v1', $http);
}

it('reads an organisation with the ETag it came with', function (): void {
    $http = new FakeClient(FakeResponse::json(['data' => organizationJson()], headers: ['ETag' => '"v1"']));

    $organization = sdk($http)->organizations()->get('01m63p2z7qwssrw1x1p7fpxgvm');

    expect($organization->postalCode)->toBeNull()
        ->and($organization->city)->toBe('York')
        ->and($organization->etag)->toBe('"v1"')
        ->and($organization->deleted())->toBeFalse();
});

it('sends only what changed, as a merge patch, with If-Match', function (): void {
    $http = new FakeClient(
        FakeResponse::json(['data' => organizationJson()], headers: ['ETag' => '"v1"']),
        FakeResponse::json(['data' => organizationJson(['city' => 'Leeds', 'email' => null])], headers: ['ETag' => '"v2"']),
    );
    $organizations = sdk($http)->organizations();

    $organization = $organizations->get('01m63p2z7qwssrw1x1p7fpxgvm');
    $updated = $organizations->update($organization, $organization->with(city: 'Leeds', email: null, country: 'GB'));

    $sent = $http->last();
    expect($sent?->getMethod())->toBe('PATCH')
        ->and($sent?->getHeaderLine('Content-Type'))->toBe('application/merge-patch+json')
        ->and($sent?->getHeaderLine('If-Match'))->toBe('"v1"')
        ->and(json_decode((string) $sent?->getBody(), true))->toBe(['email' => null, 'city' => 'Leeds'])
        ->and($updated->etag)->toBe('"v2"');
});

it('sends an update with no If-Match when the organisation came from a list', function (): void {
    $http = new FakeClient(FakeResponse::json([organizationJson()]), FakeResponse::json(['data' => organizationJson()]));
    $organizations = sdk($http)->organizations();

    $listed = $organizations->all()->take(1)[0];
    $organizations->update($listed, $listed->with(city: 'Leeds'));

    expect($listed->etag)->toBeNull()
        ->and($http->last()?->hasHeader('If-Match'))->toBeFalse();
});

it('deletes with If-Match', function (): void {
    $http = new FakeClient(FakeResponse::json(['data' => organizationJson()], headers: ['ETag' => '"v1"']), FakeResponse::empty());
    $organizations = sdk($http)->organizations();

    $organizations->delete($organizations->get('01m63p2z7qwssrw1x1p7fpxgvm'));

    $http->assertSentTo('DELETE', '/v1/organizations/01m63p2z7qwssrw1x1p7fpxgvm');
    expect($http->last()?->getHeaderLine('If-Match'))->toBe('"v1"');
});

it('creates with an Idempotency-Key, sending only the fields given', function (): void {
    $http = new FakeClient(FakeResponse::json(['data' => organizationJson()], 201, ['ETag' => '"v1"']));

    sdk($http)->organizations()->create(new NewOrganization('Rippin Group', city: 'York'), 'org-1');

    $http->assertSentTo('POST', '/v1/organizations');
    $http->assertSentJson(['name' => 'Rippin Group', 'city' => 'York']);
    expect(json_decode((string) $http->last()?->getBody(), true))->toBe(['name' => 'Rippin Group', 'city' => 'York'])
        ->and($http->last()?->getHeaderLine('Idempotency-Key'))->toContain('org-1');
});

it('lists with the search and filter in the query', function (): void {
    $http = new FakeClient(FakeResponse::json([organizationJson()]));

    sdk($http)->organizations()->all(search: 'rippin', trashed: Trashed::With)->all();

    expect($http->last()?->getUri()->getQuery())->toBe('search=rippin&trashed=with');
});

it('follows the Link header from page to page', function (): void {
    $http = new FakeClient(
        FakeResponse::json([organizationJson()], headers: ['Link' => '<http://127.0.0.1:8000/v1/organizations?cursor=abc>; rel="next"']),
        FakeResponse::json([organizationJson(['id' => '01m63p2z7qwssrw1x1p7fpxgvn'])]),
    );

    expect(sdk($http)->organizations()->all()->all())->toHaveCount(2)
        ->and($http->last()?->getUri()->getQuery())->toBe('cursor=abc');
});

it('changes only fields it knows, by name', function (array $changes): void {
    $organization = new Organization('01m63p2z7qwssrw1x1p7fpxgvm', 'Rippin Group', null, null, null, null, null, null, null, null, null, null);

    expect(fn () => $organization->with(...$changes))->toThrow(InvalidArgumentException::class);
})->with([
    'an unknown field' => [['website' => 'rippin.example']],
    'a positional value' => [['Rippin']],
    'a read-only field' => [['id' => 'x']],
    'clearing the name' => [['name' => null]],
]);
