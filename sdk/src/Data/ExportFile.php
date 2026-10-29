<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Data;

/**
 * A finished contact export, as the export.completed webhook describes it: where to download it,
 * until when.
 */
final readonly class ExportFile
{
    public function __construct(
        public string $id,
        public ?int $rows,
        public string $url,
        public ?string $expiresAt,
    ) {}
}
