<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Data;

use InvalidArgumentException;
use JustSteveKing\SdkToolbox\Http\EntityTag;

/**
 * A contact, as read, with the ETag of the version that was read, which update() and delete()
 * send in If-Match. A contact may be at one of the account's organisations.
 *
 *     $contact = $pingback->contacts()->get($id);
 *     $contact = $pingback->contacts()->update($contact, $contact->with(organizationId: $acme->id));
 */
final readonly class Contact
{
    /**
     * The fields a client writes, by property, with the API's name for each.
     */
    private const array WRITABLE = [
        'organizationId' => 'organization_id',
        'firstName' => 'first_name',
        'lastName' => 'last_name',
        'email' => 'email',
        'phone' => 'phone',
        'address' => 'address',
        'city' => 'city',
        'region' => 'region',
        'country' => 'country',
        'postalCode' => 'postal_code',
    ];

    public function __construct(
        public string $id,
        public ?string $organizationId,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $city,
        public ?string $region,
        public ?string $country,
        public ?string $postalCode,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $deletedAt,
        public ?string $etag = null,
    ) {}

    public function name(): string
    {
        return "{$this->firstName} {$this->lastName}";
    }

    public function deleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * A copy with some fields changed, to pass to update() beside the original. Name the fields,
     * and give null to clear one; organizationId: null takes the contact away from its
     * organisation.
     */
    public function with(?string ...$changes): self
    {
        $values = [];

        foreach ($changes as $field => $value) {
            if (! is_string($field) || ! array_key_exists($field, self::WRITABLE)) {
                throw new InvalidArgumentException(sprintf('Only these fields can be changed, by name: %s.', implode(', ', array_keys(self::WRITABLE))));
            }

            if (($field === 'firstName' || $field === 'lastName') && $value === null) {
                throw new InvalidArgumentException('A contact always has a first and last name.');
            }

            $values[$field] = $value;
        }

        return $this->copy($values);
    }

    /**
     * @internal the read that produced it supplies the ETag
     */
    public function withEtag(?EntityTag $etag): self
    {
        return $this->copy(['etag' => $etag?->__toString()]);
    }

    /**
     * The writable fields as the API names them, leaving out the empty ones: in a merge patch a
     * member that disappears is one to clear.
     *
     * @internal
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        $fields = [];

        foreach (self::WRITABLE as $property => $name) {
            if ($this->{$property} !== null) {
                $fields[$name] = $this->{$property};
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, string|null>  $changes
     */
    private function copy(array $changes): self
    {
        $value = static fn (string $property, ?string $current): ?string => array_key_exists($property, $changes) ? $changes[$property] : $current;

        return new self(
            id: $this->id,
            organizationId: $value('organizationId', $this->organizationId),
            firstName: $value('firstName', $this->firstName) ?? $this->firstName,
            lastName: $value('lastName', $this->lastName) ?? $this->lastName,
            email: $value('email', $this->email),
            phone: $value('phone', $this->phone),
            address: $value('address', $this->address),
            city: $value('city', $this->city),
            region: $value('region', $this->region),
            country: $value('country', $this->country),
            postalCode: $value('postalCode', $this->postalCode),
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            deletedAt: $this->deletedAt,
            etag: $value('etag', $this->etag),
        );
    }
}
