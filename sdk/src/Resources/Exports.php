<?php

declare(strict_types=1);

namespace JustSteveKing\Pingback\Resources;

use JustSteveKing\Pingback\Data\ContactExport;
use JustSteveKing\Pingback\Data\ExportFile;
use JustSteveKing\Pingback\Pingback;
use JustSteveKing\Pingback\Requests\Exports\CreateExport;
use JustSteveKing\Pingback\Requests\Exports\GetExport;

/**
 * Contact exports.
 *
 *     foreach ($pingback->exports()->create()->rows() as $contact) {
 *         echo $contact['first_name'], ' ', $contact['organization'], "\n";
 *     }
 */
final readonly class Exports
{
    public function __construct(
        private Pingback $pingback,
    ) {}

    /**
     * Starts an export and waits for it: the API answers 202, and the monitor is polled no sooner
     * than its Retry-After says until it points at the file.
     *
     * @throws \JustSteveKing\SdkToolbox\Errors\OperationTimeoutException when it's still running after `timeout` seconds
     * @throws \JustSteveKing\SdkToolbox\Errors\OperationFailedException when the API reports the export failed
     */
    public function create(?string $idempotencyKey = null, float $timeout = 300.0): ContactExport
    {
        return $this->pingback->await(new CreateExport($idempotencyKey), timeout: $timeout);
    }

    /**
     * Downloads a finished export: the one an export.completed webhook announced, or by ID.
     *
     * @throws \JustSteveKing\SdkToolbox\Errors\GoneException when it has expired
     */
    public function download(ExportFile|string $export): ContactExport
    {
        return $this->pingback->send(new GetExport($export instanceof ExportFile ? $export->id : $export));
    }
}
