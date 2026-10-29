<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Inputs;

/**
 * A contact to create. First and last name are required; organizationId puts the contact at one
 * of the account's organisations.
 */
final readonly class NewContact
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $organizationId = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $region = null,
        public ?string $country = null,
        public ?string $postalCode = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'organization_id' => $this->organizationId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'postal_code' => $this->postalCode,
        ], static fn (?string $value): bool => $value !== null);
    }
}
