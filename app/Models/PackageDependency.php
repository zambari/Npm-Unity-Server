<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageDependency extends Model
{
    protected $fillable = [
        'release_id',
        'dependency_release_id',
        'external_dependency',
        'bundle_id',
        'version',
    ];

    /**
     * Get the release that has this dependency.
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'release_id');
    }

    /**
     * Get the release that is the dependency (if internal).
     */
    public function dependencyRelease(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'dependency_release_id');
    }
}

