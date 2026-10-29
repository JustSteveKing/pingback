<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Inputs;

use JustSteveKing\Pingback\Data\Phone;

/**
 * A contact to create. First and last name are required; organizationId puts the contact at one
 * of the account's organisations, and phones is a list of Phone.
 */
final readonly class NewContact
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $organizationId = null,
        public ?string $email = null,
        /** @deprecated use phones (Pingback's ADR-0007); sent as a list of one */
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $region = null,
        public ?string $country = null,
        public ?string $postalCode = null,
        /** @var list<Phone> */
        public array $phones = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $phones = $this->phones === [] && $this->phone !== null ? [new Phone($this->phone)] : $this->phones;

        return [...array_filter([
            'organization_id' => $this->organizationId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'postal_code' => $this->postalCode,
        ], static fn (?string $value): bool => $value !== null), 'phones' => array_map(static fn (Phone $phone): array => $phone->toArray(), $phones)];
    }
}
