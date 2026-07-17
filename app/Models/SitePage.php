<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SitePage extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'body',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public static function publishedBySlug(string $slug): ?self
    {
        return static::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();
    }
}
