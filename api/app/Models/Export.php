<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExportFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Override;

/**
 * A CSV of an account's contacts (ADR-0005). It exists from the moment it is asked for; the file
 * is there from completed_at until expires_at.
 *
 * @property string $id
 * @property string $account_id
 * @property string|null $path
 * @property int|null $rows
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 */
final class Export extends Model
{
    /** @use HasFactory<ExportFactory> */
    use HasFactory;
    use HasUlids;

    /**
     * How long a finished export's file can be fetched.
     */
    public const int EXPIRES_AFTER_HOURS = 24;

    /**
     * The longest a client that does not prefer respond-async is kept waiting before it gets the
     * 202 anyway.
     */
    public const int WAIT_SECONDS = 5;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = ['path', 'rows', 'completed_at', 'expires_at'];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function completed(): bool
    {
        return null !== $this->completed_at;
    }

    public function expired(): bool
    {
        return null !== $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'rows' => 'integer',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
