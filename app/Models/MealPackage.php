<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPackage extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const DIET_TAGS = ['classic', 'veg', 'vegetarian', 'protein', 'light'];

    protected $fillable = [
        'name',
        'summary',
        'price_per_day',
        'diet_tag',
        'duration_days',
        'start_date',
        'end_date',
        'thumbnail',
        'status',
        'display_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'price_per_day' => 'integer',
        'duration_days' => 'integer',
        'display_order' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function days(): HasMany
    {
        return $this->hasMany(MealPackageDay::class)->orderBy('delivery_date');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PackageSubscription::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Single published monthly pack (product rule: only one active plan).
     */
    public static function solePublished(): ?self
    {
        return static::query()
            ->published()
            ->orderBy('display_order')
            ->orderBy('id')
            ->first();
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function assignedDaysCount(): int
    {
        return $this->days()->count();
    }

    public function expectedDaysCount(): int
    {
        if (! $this->start_date || ! $this->end_date) {
            return (int) $this->duration_days;
        }

        return $this->start_date->diffInDays($this->end_date) + 1;
    }
}
