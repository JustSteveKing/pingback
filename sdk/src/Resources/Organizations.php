<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Resources;

use JustSteveKing\Pingback\Data\Organization;
use JustSteveKing\Pingback\Inputs\NewOrganization;
use JustSteveKing\Pingback\Inputs\Trashed;
use JustSteveKing\Pingback\Pingback;
use JustSteveKing\Pingback\Requests\Organizations\CreateOrganization;
use JustSteveKing\Pingback\Requests\Organizations\DeleteOrganization;
use JustSteveKing\Pingback\Requests\Organizations\GetOrganization;
use JustSteveKing\Pingback\Requests\Organizations\ListOrganizations;
use JustSteveKing\Pingback\Requests\Organizations\RestoreOrganization;
use JustSteveKing\Pingback\Requests\Organizations\UpdateOrganization;
use JustSteveKing\SdkToolbox\Pagination\LinkPagination;
use JustSteveKing\SdkToolbox\Pagination\Paginator;

/**
 * The account's organisations.
 *
 *     foreach ($pingback->organizations()->all(search: 'acme') as $organization) {
 *         // every page, fetched as you go
 *     }
 */
final readonly class Organizations
{
    public function __construct(
        private Pingback $pingback,
    ) {}

    /**
     * Every organisation, by name, following the Link header from page to page.
     *
     * @return Paginator<Organization>
     */
    public function all(?string $search = null, ?Trashed $trashed = null): Paginator
    {
        return $this->pingback->paginate(new ListOrganizations($search, $trashed), new LinkPagination);
    }

    public function get(string $id): Organization
    {
        return $this->pingback->send(new GetOrganization($id));
    }

    public function create(NewOrganization $organization, ?string $idempotencyKey = null): Organization
    {
        return $this->pingback->send(new CreateOrganization($organization, $idempotencyKey));
    }

    /**
     * Sends what differs between the two, if the original is still the current version.
     *
     *     $pingback->organizations()->update($organization, $organization->with(city: 'Leeds'));
     *
     * @throws \JustSteveKing\SdkToolbox\Errors\PreconditionFailedException when someone changed it since it was read
     */
    public function update(Organization $original, Organization $changed): Organization
    {
        return $this->pingback->send(new UpdateOrganization($original, $changed));
    }

    /**
     * @throws \JustSteveKing\SdkToolbox\Errors\PreconditionFailedException when someone changed it since it was read
     */
    public function delete(Organization $organization): void
    {
        $this->pingback->send(new DeleteOrganization($organization));
    }

    public function restore(string $id): Organization
    {
        return $this->pingback->send(new RestoreOrganization($id));
    }
}
