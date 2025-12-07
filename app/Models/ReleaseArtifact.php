<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReleaseArtifact extends Model
{
    protected $fillable = [
        'release_id',
        'url',
        'status',
        'upload_name',
        'url_meta',
        'upload_date',
        'shasum',
    ];

    protected $casts = [
        'upload_date' => 'datetime',
    ];

    /**
     * Get the release this artifact belongs to.
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * Get all download history entries for this artifact.
     */
    public function downloadHistory(): HasMany
    {
        return $this->hasMany(DownloadHistory::class, 'artifact_id');
    }
}

