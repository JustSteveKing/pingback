<?php

declare(strict_types=1);

use JustSteveKing\Pingback\Inputs\NewOrganization;
use JustSteveKing\Pingback\Inputs\Trashed;
use JustSteveKing\SdkToolbox\Errors\ForbiddenException;
use JustSteveKing\SdkToolbox\Errors\NotFoundException;
use JustSteveKing\SdkToolbox\Errors\PreconditionFailedException;
use JustSteveKing\SdkToolbox\Errors\PreconditionRequiredException;
use JustSteveKing\SdkToolbox\Errors\UnprocessableContentException;

/*
 * Both ends of the wire, for organisations. Each test makes what it needs, so it holds against a
 * freshly seeded API and against one these tests have already run on.
 */

it('creates an organisation and reads it back, with the same ETag', function (): void {
    $organizations = pingback('organizations/create')->organizations();

    $created = $organizations->create(new NewOrganization('Contract Create Ltd', city: 'York', country: 'GB'));
    $read = $organizations->get($created->id);

    expect($read->name)->toBe('Contract Create Ltd')
        ->and($read->country)->toBe('GB')
        ->and($read->email)->toBeNull()
        ->and($read->etag)->not->toBeNull()->toBe($created->etag);
});

it('replays a create sent twice with one Idempotency-Key, rather than creating twice', function (): void {
    $organizations = pingback('organizations/idempotent')->organizations();
    $key = 'contract-'.bin2hex(random_bytes(8));

    $first = $organizations->create(new NewOrganization('Contract Idempotent Ltd'), $key);
    $second = $organizations->create(new NewOrganization('Contract Idempotent Ltd'), $key);

    expect($second->id)->toBe($first->id);
});

it('pages through organisations by following the Link header', function (): void {
    $pages = pingback('organizations/pages')->organizations()->all()->pages();

    $first = $pages->current();
    $pages->next();
    $second = $pages->current();

    expect($first->items)->toHaveCount(25)
        ->and($second->items)->not->toBeEmpty()
        ->and($second->items[0]->id)->not->toBe($first->items[0]->id);
});

it('searches, and leaves deleted organisations out unless asked', function (): void {
    $organizations = pingback('organizations/search')->organizations();
    $kept = $organizations->create(new NewOrganization('Contract Search Kept'));
    $gone = $organizations->create(new NewOrganization('Contract Search Gone'));
    $organizations->delete($gone);

    $found = array_map(fn ($organization) => $organization->id, $organizations->all(search: 'contract search')->all());
    $withDeleted = array_map(fn ($organization) => $organization->id, $organizations->all(search: 'contract search', trashed: Trashed::With)->all());

    expect($found)->toContain($kept->id)->not->toContain($gone->id)
        ->and($withDeleted)->toContain($kept->id, $gone->id);
});

it('updates with a merge patch of what changed, and gets the new ETag', function (): void {
    $organizations = pingback('organizations/update')->organizations();
    $organization = $organizations->create(new NewOrganization('Contract Update Ltd', email: 'hello@contract.example', city: 'York'));

    $updated = $organizations->update($organization, $organization->with(city: 'Leeds', email: null));

    expect($updated->city)->toBe('Leeds')
        ->and($updated->email)->toBeNull()
        ->and($updated->name)->toBe('Contract Update Ltd')
        ->and($updated->etag)->not->toBe($organization->etag)
        ->and($updated->etag)->toBe($organizations->get($organization->id)->etag);
});

it('refuses an update made against a stale read: PreconditionFailedException, not a lost update', function (): void {
    $organizations = pingback('organizations/stale')->organizations();
    $organization = $organizations->create(new NewOrganization('Contract Stale Ltd'));

    $organizations->update($organization, $organization->with(city: 'Leeds'));

    try {
        $organizations->update($organization, $organization->with(city: 'York'));
        $this->fail('Expected PreconditionFailedException');
    } catch (PreconditionFailedException $e) {
        expect($e->problem?->type)->toBe('https://apiguide.dev/errors/precondition-failed');
    }

    expect($organizations->get($organization->id)->city)->toBe('Leeds');
});

it('refuses an update with no ETag to send: PreconditionRequiredException', function (): void {
    $organizations = pingback('organizations/unconditional')->organizations();
    $organizations->create(new NewOrganization('Contract Unconditional Ltd'));
    $listed = $organizations->all(search: 'contract unconditional')->first();

    expect(fn () => $organizations->update($listed, $listed->with(city: 'Leeds')))
        ->toThrow(PreconditionRequiredException::class);
});

it('deletes with If-Match, and restores', function (): void {
    $organizations = pingback('organizations/delete')->organizations();
    $organization = $organizations->create(new NewOrganization('Contract Delete Ltd'));

    $organizations->delete($organization);
    $deleted = $organizations->get($organization->id);
    $restored = $organizations->restore($organization->id);

    expect($deleted->deleted())->toBeTrue()
        ->and($deleted->etag)->not->toBe($organization->etag)
        ->and($restored->deleted())->toBeFalse()
        ->and($restored->etag)->not->toBe($deleted->etag);
});

it('refuses a delete made against a stale read', function (): void {
    $organizations = pingback('organizations/stale-delete')->organizations();
    $organization = $organizations->create(new NewOrganization('Contract Stale Delete Ltd'));
    $organizations->update($organization, $organization->with(city: 'Leeds'));

    expect(fn () => $organizations->delete($organization))->toThrow(PreconditionFailedException::class)
        ->and($organizations->get($organization->id)->deleted())->toBeFalse();
});

it('turns a malformed organisation into UnprocessableContentException, naming the field', function (): void {
    try {
        pingback('organizations/invalid')->organizations()->create(new NewOrganization('Contract Invalid Ltd', country: 'gb'));
        $this->fail('Expected UnprocessableContentException');
    } catch (UnprocessableContentException $e) {
        expect($e->problem?->type)->toBe('https://apiguide.dev/errors/validation-failed')
            ->and($e->problem?->extensions['errors'] ?? [])->toHaveKey('country');
    }
});

it('cannot see another account\'s organisation: NotFoundException, never 403', function (): void {
    $organization = pingback('organizations/other-account-create')->organizations()->create(new NewOrganization('Contract Private Ltd'));

    expect(fn () => pingback('organizations/other-account-get', token: otherAccountToken())->organizations()->get($organization->id))
        ->toThrow(NotFoundException::class);
});

it('turns a key without organizations:write into ForbiddenException', function (): void {
    expect(fn () => pingback('organizations/forbidden', token: apiKey(['organizations:read']))->organizations()->create(new NewOrganization('Contract Forbidden Ltd')))
        ->toThrow(ForbiddenException::class);
});
