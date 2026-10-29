<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Organization>
 */
final class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'region' => fake()->state(),
            'country' => fake()->randomElement(['US', 'CA', 'GB']),
            'postal_code' => fake()->postcode(),
        ];
    }
}
