<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasRevision;
use Database\Factories\OrganizationFactory;
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
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
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
 */
final class Organization extends Model implements HasValidators
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;
    use HasRevision {
        entityTagVersion as revisionTagVersion;
    }
    use HasUlids;
    use SoftDeletes;

    /**
     * Every field a client writes, in the order the API shows them.
     */
    public const array FIELDS = ['name', 'email', 'phone', 'address', 'city', 'region', 'country', 'postal_code'];

    /**
     * The database defaults, so a model fresh from create() has an ETag without a reload.
     *
     * @var array<string, int>
     */
    #[Override]
    protected $attributes = ['revision' => 1];

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
     * A soft delete writes deleted_at with a query of its own, which fires no "updating" event and
     * so leaves the revision alone. The representation still changes, so the ETag must too. A
     * restore is an ordinary save and moves the revision on, so no tag repeats.
     */
    protected function entityTagVersion(): ?string
    {
        $version = $this->revisionTagVersion();

        return null !== $version && $this->trashed() ? $version . '-deleted' : $version;
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
        ];
    }
}
