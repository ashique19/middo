<?php

namespace App\Models;

use Database\Factories\UserFactory;
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
    'mobile', 
    'password', 
    'role_id', 
    'status',                // Added
    'is_mobile_verified',    // Added
    'address',               // Included as per your migration
    'city_id',               // Included as per your migration
    'area_id'                // Included as per your migration
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

    public function hasPermission($permissionName) {
        return $this->role && $this->role->permissions()->where('name', $permissionName)->exists();
    }
}