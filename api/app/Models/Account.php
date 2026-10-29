<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * Everything Pingback stores belongs to an account. In v1 an account has one user, its owner
 * (ADR-0003).
 *
 * @property string $id
 * @property string $owner_id
 * @property string $name
 */
final class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;
    use HasUlids;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = ['name'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
