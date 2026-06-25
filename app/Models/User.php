<?php

namespace App\Models;

use App\Models\MiddoBox;
use App\Models\Order;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Area;
use App\Models\City;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// Updated the Fillable attribute
#[Fillable([
    'first_name', 
    'last_name', 
    'full_name',
    'balance',
    'mobile', 
    'password', 
    'role_id', 
    'status',                // Added
    'is_mobile_verified',    // Added
    'address',               // Included as per your migration
    'city_id',               // Included as per your migration
    'area_id',               // Included as per your migration
    'balance'                // Included as per your migration
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        ];
    }

    // ... your existing relationship methods (role, city, area)
    public function role() {
        return $this->belongsTo(\App\Models\Role::class);
    }

    public function city() {
        return $this->belongsTo(City::class);
    }

    public function area() {
        return $this->belongsTo(Area::class);
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

    public function getNameAttribute(): string
    {
        $name = trim(sprintf('%s %s', $this->first_name, $this->last_name));
        return $name !== '' ? $name : ($this->first_name ?? $this->last_name ?? '');
    }

    public function hasPermission($permissionName) {
        return $this->role && $this->role->permissions()->where('name', $permissionName)->exists();
    }
}