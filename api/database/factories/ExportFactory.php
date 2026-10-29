<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Export;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Export>
 */
final class ExportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
        ];
    }
}
