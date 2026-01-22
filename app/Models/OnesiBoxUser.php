<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OnesiBoxPermission;
use App\Traits\LogsActivityAllDirty;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for the OnesiBox-User (caregiver) relationship.
 *
 * @property int $id
 * @property int $onesi_box_id
 * @property int $user_id
 * @property OnesiBoxPermission $permission
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read OnesiBox $onesiBox
 * @property-read User $user
 */
class OnesiBoxUser extends Pivot
{
    use LogsActivityAllDirty;

    public $incrementing = true;

    protected $table = 'onesi_box_user';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'onesi_box_id',
        'user_id',
        'permission',
    ];

    /**
     * Get the OnesiBox for this pivot record.
     *
     * @return BelongsTo<OnesiBox, $this>
     */
    public function onesiBox(): BelongsTo
    {
        return $this->belongsTo(OnesiBox::class);
    }

    /**
     * Get the User (caregiver) for this pivot record.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permission' => OnesiBoxPermission::class,
        ];
    }
}
