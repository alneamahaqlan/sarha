<?php

namespace App\Models;

use App\Models\Concerns\HasDemoData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Article extends Model
{
    use SoftDeletes, HasDemoData;

    protected $fillable = [
        'clinic_id', 'title', 'slug', 'body', 'cover_image',
        'meta_description', 'tags', 'is_published', 'published_at',
        'views_count', 'ai_generated',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_published' => 'boolean',
            'ai_generated' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Article $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title) . '-' . Str::random(6);
            }
        });
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}
