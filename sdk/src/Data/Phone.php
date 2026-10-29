<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Data;

/**
 * One of a contact's phone numbers, with an optional label such as work or mobile.
 */
final readonly class Phone
{
    public function __construct(
        public string $number,
        public ?string $label = null,
    ) {}

    /**
     * @return array{number: string, label: string|null}
     */
    public function toArray(): array
    {
        return ['number' => $this->number, 'label' => $this->label];
    }
}
