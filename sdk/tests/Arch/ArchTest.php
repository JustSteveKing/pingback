<?php

declare(strict_types=1);

arch('strict types everywhere')
    ->expect('JustSteveKing\Pingback')
    ->toUseStrictTypes();

arch('data, inputs and requests are immutable')
    ->expect(['JustSteveKing\Pingback\Data', 'JustSteveKing\Pingback\Requests'])
    ->classes()
    ->toBeReadonly();

arch('no debugging left behind')
    ->preset()->php();
