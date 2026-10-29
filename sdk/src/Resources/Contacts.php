<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Resources;

use JustSteveKing\Pingback\Data\Contact;
use JustSteveKing\Pingback\Data\Organization;
use JustSteveKing\Pingback\Inputs\NewContact;
use JustSteveKing\Pingback\Inputs\Trashed;
use JustSteveKing\Pingback\Pingback;
use JustSteveKing\Pingback\Requests\Contacts\CreateContact;
use JustSteveKing\Pingback\Requests\Contacts\DeleteContact;
use JustSteveKing\Pingback\Requests\Contacts\GetContact;
use JustSteveKing\Pingback\Requests\Contacts\ListContacts;
use JustSteveKing\Pingback\Requests\Contacts\RestoreContact;
use JustSteveKing\Pingback\Requests\Contacts\UpdateContact;
use JustSteveKing\SdkToolbox\Pagination\LinkPagination;
use JustSteveKing\SdkToolbox\Pagination\Paginator;

/**
 * The account's contacts.
 *
 *     foreach ($pingback->contacts()->all(at: $acme) as $contact) {
 *         echo $contact->name();
 *     }
 */
final readonly class Contacts
{
    public function __construct(
        private Pingback $pingback,
    ) {}

    /**
     * Every contact, by last name then first name, following the Link header from page to page.
     * Search matches first name, last name, email and organisation name; `at` keeps to the
     * contacts at one organisation.
     *
     * @return Paginator<Contact>
     */
    public function all(?string $search = null, ?Trashed $trashed = null, Organization|string|null $at = null): Paginator
    {
        $organizationId = $at instanceof Organization ? $at->id : $at;

        return $this->pingback->paginate(new ListContacts($search, $trashed, $organizationId), new LinkPagination);
    }

    public function get(string $id): Contact
    {
        return $this->pingback->send(new GetContact($id));
    }

    public function create(NewContact $contact, ?string $idempotencyKey = null): Contact
    {
        return $this->pingback->send(new CreateContact($contact, $idempotencyKey));
    }

    /**
     * Sends what differs between the two, if the original is still the current version.
     *
     *     $pingback->contacts()->update($contact, $contact->with(organizationId: null));
     *
     * @throws \JustSteveKing\SdkToolbox\Errors\PreconditionFailedException when someone changed it since it was read
     */
    public function update(Contact $original, Contact $changed): Contact
    {
        return $this->pingback->send(new UpdateContact($original, $changed));
    }

    /**
     * @throws \JustSteveKing\SdkToolbox\Errors\PreconditionFailedException when someone changed it since it was read
     */
    public function delete(Contact $contact): void
    {
        $this->pingback->send(new DeleteContact($contact));
    }

    public function restore(string $id): Contact
    {
        return $this->pingback->send(new RestoreContact($id));
    }
}
