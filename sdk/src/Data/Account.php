<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Data;

/**
 * The account a token works in. Everything Pingback stores belongs to one.
 *
 * Timestamps are RFC 3339 strings, as the API sends them.
 */
final readonly class Account
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {}
}
