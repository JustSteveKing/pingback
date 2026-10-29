<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasRevision;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use JustSteveKing\Toolbox\ConditionalRequests\Contracts\HasValidators;
use Override;

/**
 * @property string $id
 * @property string $account_id
 * @property string|null $organization_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property list<array{number: string, label: string|null}> $phones
 * @property string|null $address
 * @property string|null $city
 * @property string|null $region
 * @property string|null $country
 * @property string|null $postal_code
 * @property int $revision
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Account $account
 * @property-read Organization|null $organization
 */
final class Contact extends Model implements HasValidators
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;
    use HasRevision;
    use HasUlids;
    use SoftDeletes;

    /**
     * Every field a client writes, in the order the API shows them.
     */
    public const array FIELDS = ['organization_id', 'first_name', 'last_name', 'email', 'phones', 'address', 'city', 'region', 'country', 'postal_code'];

    /**
     * The database defaults, so a model fresh from create() has an ETag without a reload, and a
     * list of phones that is empty rather than null.
     *
     * @var array<string, int|string>
     */
    #[Override]
    protected $attributes = ['revision' => 1, 'phones' => '[]'];

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = self::FIELDS;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Deleting an organisation keeps it, so a contact still points at it.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    /**
     * The first number, which is what the deprecated phone field shows (ADR-0007).
     */
    public function primaryPhone(): ?string
    {
        return $this->phones[0]['number'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'phones' => 'array',
        ];
    }
}
