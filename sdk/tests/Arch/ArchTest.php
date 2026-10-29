<?php

declare(strict_types=1);

arch('strict types everywhere')
    ->expect('JustSteveKing\Pingback')
    ->toUseStrictTypes();

arch('data, inputs and requests are immutable')
    ->expect(['JustSteveKing\Pingback\Data', 'JustSteveKing\Pingback\Inputs', 'JustSteveKing\Pingback\Requests', 'JustSteveKing\Pingback\Resources', 'JustSteveKing\Pingback\Webhooks'])
    ->classes()
    ->toBeReadonly();

arch('no debugging left behind')
    ->preset()->php();
