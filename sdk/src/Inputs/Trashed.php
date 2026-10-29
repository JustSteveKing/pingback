<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Inputs;

/**
 * Whether a list includes deleted records. Leave it out and they are left out.
 */
enum Trashed: string
{
    /** Deleted records as well as the rest. */
    case With = 'with';

    /** Deleted records and nothing else. */
    case Only = 'only';
}
