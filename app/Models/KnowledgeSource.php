<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'drive_url', 'drive_folder_id', 'is_active'])]
class KnowledgeSource extends Model
{
    protected $attributes = [
        'is_active' => true,
        'sync_status' => 'never',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (KnowledgeSource $source) {
            if ($source->isDirty('drive_url') || blank($source->drive_folder_id)) {
                $source->drive_folder_id = static::extractFolderId($source->drive_url) ?? $source->drive_folder_id;
            }
        });
    }

    public static function extractFolderId(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        if (preg_match('~/folders/([A-Za-z0-9_-]+)~', $url, $m) || preg_match('~[?&]id=([A-Za-z0-9_-]+)~', $url, $m)) {
            return $m[1];
        }

        return preg_match('~^[A-Za-z0-9_-]{10,}$~', $url) ? $url : null;
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    public function chunks(): HasManyThrough
    {
        return $this->hasManyThrough(KnowledgeChunk::class, KnowledgeDocument::class);
    }
}
