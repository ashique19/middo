<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    public const STATUS_LEAD = 'lead';

    public const STATUS_APPOINTMENT_SET = 'appointment_set';

    public const STATUS_VISITED = 'visited';

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'name',
        'address',
        'city_id',
        'area_id',
        'hr_name',
        'hr_mobile',
        'status',
        'notes',
        'created_by',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(CompanyAppointment::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(User::class, 'company_id');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_LEAD => 'Lead',
            self::STATUS_APPOINTMENT_SET => 'Appointment set',
            self::STATUS_VISITED => 'Visited',
            self::STATUS_ACTIVE => 'Active',
            default => ucfirst($status),
        };
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_LEAD,
            self::STATUS_APPOINTMENT_SET,
            self::STATUS_VISITED,
            self::STATUS_ACTIVE,
        ];
    }

    public function markActiveIfHasEmployees(): void
    {
        if ($this->employees()->exists() && $this->status !== self::STATUS_ACTIVE) {
            $this->update(['status' => self::STATUS_ACTIVE]);
        }
    }
}
