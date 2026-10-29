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

    /**
     * @param  string  $url  where the file will be, built while there is a request to build it
     *                       from: a queue worker only knows APP_URL, which need not be the host the
     *                       client used
     */
    public function __construct(
        public string $exportId,
        public string $url,
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
                    $contact->primaryPhone(),
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

        DB::transaction(function () use ($export, $path, $rows, $webhooks): void {
            $export->update([
                'path' => $path,
                'rows' => $rows,
                'completed_at' => now(),
                'expires_at' => now()->addHours(Export::EXPIRES_AFTER_HOURS),
            ]);

            $owner = $export->account->owner;

            if (null !== $owner) {
                $webhooks->dispatch($owner, new ExportCompleted($export, $this->url));
            }
        });

        $operation->resultAt($this->url);
    }
}
