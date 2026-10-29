<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * Registering creates an account the new user owns (ADR-0003). It listens for Kit's own Registered
 * event rather than changing Kit's registration, which stays upstream (ADR-0002). It runs in the
 * request, not on a queue, so the account exists before the user's first call.
 */
final class CreateAccountForNewUser
{
    public function handle(Registered $event): void
    {
        $user = $event->user;

        if ( ! $user instanceof User) {
            return;
        }

        $account = new Account(['name' => $user->name]);
        $account->owner()->associate($user);
        $account->save();
    }
}
