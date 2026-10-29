<?php

declare(strict_types=1);

namespace App\Webhooks\Events;

use App\Models\Export;
use App\Webhooks\WebhookEvent;

/**
 * A contact export finished, so there is no need to keep polling its monitor (ADR-0005).
 */
final readonly class ExportCompleted implements WebhookEvent
{
    public const string TYPE = 'export.completed';

    public function __construct(
        private Export $export,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function subject(): string
    {
        return $this->export->id;
    }

    /**
     * @return array{id: string, rows: int|null, url: string, expires_at: string|null}
     */
    public function data(): array
    {
        return [
            'id' => $this->export->id,
            'rows' => $this->export->rows,
            'url' => route('v1.exports.show', $this->export),
            'expires_at' => $this->export->expires_at?->toAtomString(),
        ];
    }
}
