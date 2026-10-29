<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Export;
use App\Webhooks\Events\ExportCompleted;
use App\Webhooks\Webhooks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use JustSteveKing\Toolbox\AsyncOperations\CurrentOperation;
use RuntimeException;

/**
 * Writes an account's contacts, deleted ones left out, to a CSV, then points the operation's
 * monitor at it and announces it.
 */
final class ExportContacts implements ShouldQueue
{
    use Queueable;

    /**
     * The columns, in order, as the file's header row names them.
     */
    public const array COLUMNS = ['id', 'first_name', 'last_name', 'email', 'phone', 'organization', 'address', 'city', 'region', 'country', 'postal_code'];

    public function __construct(
        public string $exportId,
    ) {}

    public function handle(CurrentOperation $operation, Webhooks $webhooks): void
    {
        $export = Export::query()->with('account.owner')->findOrFail($this->exportId);
        $csv = fopen('php://temp', 'w+') ?: throw new RuntimeException('Could not open a buffer for the export.');
        $rows = 0;

        fputcsv($csv, self::COLUMNS, escape: '');

        Contact::query()
            ->whereBelongsTo($export->account)
            ->with('organization')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->lazy()
            ->each(static function (Contact $contact) use ($csv, &$rows): void {
                fputcsv($csv, [
                    $contact->id,
                    $contact->first_name,
                    $contact->last_name,
                    $contact->email,
                    $contact->phone,
                    $contact->organization?->name,
                    $contact->address,
                    $contact->city,
                    $contact->region,
                    $contact->country,
                    $contact->postal_code,
                ], escape: '');
                $rows++;
            });

        rewind($csv);
        $path = "exports/{$export->id}.csv";
        Storage::put($path, $csv);
        fclose($csv);

        DB::transaction(static function () use ($export, $path, $rows, $webhooks): void {
            $export->update([
                'path' => $path,
                'rows' => $rows,
                'completed_at' => now(),
                'expires_at' => now()->addHours(Export::EXPIRES_AFTER_HOURS),
            ]);

            $owner = $export->account->owner;

            if (null !== $owner) {
                $webhooks->dispatch($owner, new ExportCompleted($export));
            }
        });

        $operation->resultAt(route('v1.exports.show', $export));
    }
}
