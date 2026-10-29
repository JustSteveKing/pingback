<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Inputs;

/**
 * An organisation to create. Only the name is required; country is a two-letter ISO 3166-1 code
 * in capitals, such as GB.
 */
final readonly class NewOrganization
{
    public function __construct(
        public string $name,
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
            'name' => $this->name,
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
