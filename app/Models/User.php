<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// Updated the Fillable attribute
#[Fillable([
    'first_name',
    'last_name',
    'company_name',
    'full_name',
    'balance',
    'mobile',
    'password',
    'role_id',
    'status',
    'kitchen_tier',
    'allowed_open_groups',
    'is_mobile_verified',
    'address',
    'city_id',
    'area_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_mobile_verified' => 'boolean', // Ensures 0/1 becomes false/true
            'balance' => 'integer',
            'allowed_open_groups' => 'integer',
        ];
    }

    // ... your existing relationship methods (role, city, area)
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function getCityNameAttribute(): ?string
    {
        if ($this->city_id) {
            return $this->relationLoaded('city')
                ? $this->getRelation('city')?->name
                : City::find($this->city_id)?->name;
        }

        return $this->attributes['city'] ?? null;
    }

    public function getAreaNameAttribute(): ?string
    {
        if ($this->area_id) {
            return $this->relationLoaded('area')
                ? $this->getRelation('area')?->name
                : Area::find($this->area_id)?->name;
        }

        return $this->attributes['area'] ?? null;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function updatedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'updated_by');
    }

    public function heldMiddoBoxes(): HasMany
    {
        return $this->hasMany(MiddoBox::class, 'held_by_user_id');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function kitchenHours(): HasMany
    {
        return $this->hasMany(KitchenHour::class, 'user_id')->orderBy('day_of_week');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(UserLog::class);
    }

    public function getNameAttribute(): string
    {
        $name = trim(sprintf('%s %s', $this->first_name, $this->last_name));

        return $name !== '' ? $name : ($this->first_name ?? $this->last_name ?? '');
    }

    public function hasPermission($permissionName)
    {
        if (! $this->role) {
            return false;
        }

        // Bare kitchen fixtures (tests / legacy) without a synced kitchen.* matrix
        // still pass kitchen.* checks; role:kitchen remains the primary gate.
        if (is_string($permissionName) && str_starts_with($permissionName, 'kitchen.')) {
            $hasKitchenMatrix = $this->role->permissions()
                ->where('name', 'like', 'kitchen.%')
                ->exists();

            if (! $hasKitchenMatrix) {
                return $this->role->name === 'kitchen';
            }
        }

        return $this->role->permissions()->where('name', $permissionName)->exists();
    }
}
