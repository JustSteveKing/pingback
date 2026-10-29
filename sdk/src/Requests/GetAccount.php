<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Requests;

use JustSteveKing\Pingback\Data\Account;
use JustSteveKing\SdkToolbox\Http\Method;
use JustSteveKing\SdkToolbox\Http\Response;
use JustSteveKing\SdkToolbox\Requests\Request;

/**
 * The account the token works in. Needs the account:read ability.
 *
 * @implements Request<Account>
 */
final readonly class GetAccount implements Request
{
    public function method(): Method
    {
        return Method::Get;
    }

    public function path(): string
    {
        return '/account';
    }

    public function result(Response $response): Account
    {
        return $response->map(Account::class, at: 'data');
    }
}
