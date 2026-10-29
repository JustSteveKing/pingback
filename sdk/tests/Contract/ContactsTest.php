<?php

declare(strict_types=1);

use JustSteveKing\Pingback\Inputs\NewContact;
use JustSteveKing\Pingback\Inputs\NewOrganization;
use JustSteveKing\SdkToolbox\Errors\NotFoundException;
use JustSteveKing\SdkToolbox\Errors\PreconditionFailedException;
use JustSteveKing\SdkToolbox\Errors\UnprocessableContentException;

/*
 * Both ends of the wire, for contacts and the organisations they are at. The standards are the
 * ones the organisation tests prove; these prove the relationship.
 */

it('creates a contact at an organisation, and finds it among that organisation\'s contacts', function (): void {
    $pingback = pingback('contacts/at-organization');
    $organization = $pingback->organizations()->create(new NewOrganization('Contract Contacts Ltd'));

    $contact = $pingback->contacts()->create(new NewContact('Ada', 'Lovelace', organizationId: $organization->id));
    $at = $pingback->contacts()->all(at: $organization)->all();

    expect($contact->organizationId)->toBe($organization->id)
        ->and(array_map(fn ($contact) => $contact->id, $at))->toBe([$contact->id]);
});

it('finds a contact by its organisation\'s name', function (): void {
    $pingback = pingback('contacts/search');
    $organization = $pingback->organizations()->create(new NewOrganization('Contract Searchable Engines'));
    $contact = $pingback->contacts()->create(new NewContact('Grace', 'Hopper', organizationId: $organization->id));

    $found = array_map(fn ($contact) => $contact->id, $pingback->contacts()->all(search: 'searchable engines')->all());

    expect($found)->toContain($contact->id);
});

it('moves a contact between organisations, and away from all of them', function (): void {
    $pingback = pingback('contacts/move');
    $from = $pingback->organizations()->create(new NewOrganization('Contract From Ltd'));
    $to = $pingback->organizations()->create(new NewOrganization('Contract To Ltd'));
    $contact = $pingback->contacts()->create(new NewContact('Ada', 'Lovelace', organizationId: $from->id));

    $moved = $pingback->contacts()->update($contact, $contact->with(organizationId: $to->id));
    $alone = $pingback->contacts()->update($moved, $moved->with(organizationId: null));

    expect($moved->organizationId)->toBe($to->id)
        ->and($alone->organizationId)->toBeNull();
});

it('refuses another account\'s organisation as if it did not exist', function (): void {
    $theirs = pingback('contacts/other-organization-create', token: otherAccountToken())->organizations()->create(new NewOrganization('Contract Theirs Ltd'));

    try {
        pingback('contacts/other-organization')->contacts()->create(new NewContact('Ada', 'Lovelace', organizationId: $theirs->id));
        $this->fail('Expected UnprocessableContentException');
    } catch (UnprocessableContentException $e) {
        expect($e->problem?->extensions['errors'] ?? [])->toHaveKey('organization_id');
    }
});

it('keeps a contact editable after its organisation is deleted', function (): void {
    $pingback = pingback('contacts/organization-deleted');
    $organization = $pingback->organizations()->create(new NewOrganization('Contract Closing Ltd'));
    $contact = $pingback->contacts()->create(new NewContact('Ada', 'Lovelace', organizationId: $organization->id));
    $pingback->organizations()->delete($organization);

    $updated = $pingback->contacts()->update($contact, $contact->with(city: 'London'));

    expect($updated->city)->toBe('London')
        ->and($updated->organizationId)->toBe($organization->id);
});

it('guards a contact against a stale update, deletes and restores it', function (): void {
    $contacts = pingback('contacts/lifecycle')->contacts();
    $contact = $contacts->create(new NewContact('Ada', 'Lovelace'));
    $contacts->update($contact, $contact->with(city: 'London'));

    expect(fn () => $contacts->update($contact, $contact->with(city: 'Paris')))->toThrow(PreconditionFailedException::class);

    $current = $contacts->get($contact->id);
    $contacts->delete($current);

    expect($contacts->get($contact->id)->deleted())->toBeTrue()
        ->and($contacts->restore($contact->id)->deleted())->toBeFalse();
});

it('cannot see another account\'s contact', function (): void {
    $contact = pingback('contacts/other-account-create')->contacts()->create(new NewContact('Ada', 'Lovelace'));

    expect(fn () => pingback('contacts/other-account-get', token: otherAccountToken())->contacts()->get($contact->id))
        ->toThrow(NotFoundException::class);
});
