<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nav extends Model
{

    protected $fillable = ['order', 'title', 'route_name', 'parent_id', 'role_id'];

    public function role() {
        return $this->belongsTo(Role::class);
    }

    // App\Models\Nav.php
    public function children() {
        return $this->hasMany(Nav::class, 'parent_id')->orderBy('order');
    }
    
}
