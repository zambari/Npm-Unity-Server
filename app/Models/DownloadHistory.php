<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadHistory extends Model
{
    protected $table = 'download_history';

    public $timestamps = false;

    protected $fillable = [
        'release_id',
        'artifact_id',
        'additional_data',
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'additional_data' => 'array',
    ];

    /**
     * Get the release associated with this download.
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /**
     * Get the artifact that was downloaded.
     */
    public function artifact(): BelongsTo
    {
        return $this->belongsTo(ReleaseArtifact::class, 'artifact_id');
    }
}

