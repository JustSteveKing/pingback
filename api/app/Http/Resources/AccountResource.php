<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AccountResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, created_at: ?string, updated_at: ?string}
     */
    public function toArray(Request $request): array
    {
        /** @var Account $account */
        $account = $this->resource;

        return [
            'id' => $account->id,
            'name' => $account->name,
            'created_at' => $account->created_at?->toAtomString(),
            'updated_at' => $account->updated_at?->toAtomString(),
        ];
    }
}
