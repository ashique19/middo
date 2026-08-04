<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenHour extends Model
{
    public const DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    protected $fillable = [
        'user_id',
        'day_of_week',
        'opens_at',
        'closes_at',
        'is_closed',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed' => 'boolean',
    ];

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function dayLabel(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'Day '.$this->day_of_week;
    }

    public function hoursLabel(): string
    {
        if ($this->is_closed) {
            return 'Closed';
        }

        $open = $this->formatTime($this->opens_at);
        $close = $this->formatTime($this->closes_at);

        if ($open === null || $close === null) {
            return '—';
        }

        return "{$open} – {$close}";
    }

    protected function formatTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('g:i A');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
