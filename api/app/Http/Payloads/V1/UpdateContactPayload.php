<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final readonly class UpdateContactPayload
{
    public function __construct(
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
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = self::validatedData($request);

        return new self(
            organizationId: self::optional($data, 'organization_id'),
            firstName: Arr::string($data, 'first_name'),
            lastName: Arr::string($data, 'last_name'),
            email: self::optional($data, 'email'),
            phone: self::optional($data, 'phone'),
            address: self::optional($data, 'address'),
            city: self::optional($data, 'city'),
            region: self::optional($data, 'region'),
            country: self::optional($data, 'country'),
            postalCode: self::optional($data, 'postal_code'),
        );
    }

    /**
     * Every field, for the model: a field left out of the request is stored as null.
     *
     * @return array<string, string|null>
     */
    public function attributes(): array
    {
        return array_combine(Contact::FIELDS, [
            $this->organizationId,
            $this->firstName,
            $this->lastName,
            $this->email,
            $this->phone,
            $this->address,
            $this->city,
            $this->region,
            $this->country,
            $this->postalCode,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function optional(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function validatedData(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $request instanceof FormRequest ? $request->validated() : $request->all();

        return $data;
    }
}
