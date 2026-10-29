<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Data;

use InvalidArgumentException;
use JustSteveKing\SdkToolbox\Http\EntityTag;

/**
 * An organisation, as read, with the ETag of the version that was read. That ETag is what makes
 * changing it safe: update() and delete() send it in If-Match, so they only apply if nobody
 * changed the organisation in between.
 *
 *     $organization = $pingback->organizations()->get($id);
 *     $organization = $pingback->organizations()->update($organization, $organization->with(city: 'Leeds', email: null));
 */
final readonly class Organization
{
    /**
     * The fields a client writes, by property, with the API's name for each.
     */
    private const array WRITABLE = [
        'name' => 'name',
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
        public string $name,
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

    public function deleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * A copy with some fields changed, to pass to update() beside the original. Name the fields,
     * and give null to clear one:
     *
     *     $organization->with(city: 'Leeds', email: null)
     *
     * The copy keeps the original's ETag, but only the original's is sent.
     */
    public function with(?string ...$changes): self
    {
        $values = [];

        foreach ($changes as $field => $value) {
            if (! is_string($field) || ! array_key_exists($field, self::WRITABLE)) {
                throw new InvalidArgumentException(sprintf('Only these fields can be changed, by name: %s.', implode(', ', array_keys(self::WRITABLE))));
            }

            if ($field === 'name' && $value === null) {
                throw new InvalidArgumentException('An organisation always has a name.');
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
            name: $value('name', $this->name) ?? $this->name,
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
