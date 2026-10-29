<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group(name: 'Account')]
#[Endpoint(
    title: 'Get the Account',
    description: 'Return the account the bearer token works in: everything Pingback stores belongs to it. The response carries an ETag: send it back in If-None-Match and an unchanged account returns 304 with no body.',
)]
#[Authenticated]
#[Header('If-None-Match', '"a1b2c3d4e5f6"')]
#[ResponseFromApiResource(name: AccountResource::class, model: Account::class, status: 200, description: 'The account.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/unauthorized',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
    ],
    status: 401,
    description: 'Authentication failed.',
)]
final class ShowAccountController
{
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        $account = Account::query()->where('owner_id', $user->id)->firstOrFail();

        return AccountResource::make($account)->response();
    }
}
