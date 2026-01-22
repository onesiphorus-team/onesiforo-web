<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\LogsActivityAllDirty;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Represents an elderly person assisted through an OnesiBox appliance.
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $phone
 * @property string|null $street
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $province
 * @property array<int, array{name: string, phone: string, relationship?: string}>|null $emergency_contacts
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read OnesiBox|null $onesiBox
 * @property-read string $full_name
 * @property-read string|null $full_address
 */
class Recipient extends Model
{
    /** @use HasFactory<\Database\Factories\RecipientFactory> */
    use HasFactory;

    use LogsActivityAllDirty;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'street',
        'city',
        'postal_code',
        'province',
        'emergency_contacts',
        'notes',
    ];

    /**
     * Get the OnesiBox associated with this recipient.
     *
     * @return HasOne<OnesiBox, $this>
     */
    public function onesiBox(): HasOne
    {
        return $this->hasOne(OnesiBox::class);
    }

    /**
     * Get the recipient's full name.
     */
    protected function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the recipient's formatted address.
     */
    protected function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->street,
            $this->postal_code,
            $this->city,
            $this->province ? "({$this->province})" : null,
        ]);

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emergency_contacts' => 'array',
        ];
    }
}
