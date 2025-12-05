<?php

namespace App\Models;

use App\Enums\ReleaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Release extends Model
{
    protected $fillable = [
        'package_id',
        'version',
        'channel',
        'release_status',
        'changelog',
        'user_id',
        'create_time',
        'update_time',
    ];

    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    protected $casts = [
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    /**
     * Get the package this release belongs to.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the user who created this release.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all artifacts for this release.
     */
    public function artifacts(): HasMany
    {
        return $this->hasMany(ReleaseArtifact::class);
    }

    /**
     * Get all download history entries for this release.
     */
    public function downloadHistory(): HasMany
    {
        return $this->hasMany(DownloadHistory::class);
    }

    /**
     * Get dependencies where this release is the dependent.
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(PackageDependency::class, 'release_id');
    }

    /**
     * Get dependencies where this release is the dependency.
     */
    public function dependentReleases(): HasMany
    {
        return $this->hasMany(PackageDependency::class, 'dependency_release_id');
    }

    /**
     * Get the total count of all releases.
     */
    public static function totalReleaseCount(): int
    {
        return self::count();
    }

    /**
     * Get the count of published releases.
     */
    public static function publishedReleaseCount(): int
    {
        return self::where('release_status', ReleaseStatus::PUBLISHED)->count();
    }

    /**
     * Get the processed artifact file size in KB as a formatted string.
     * 
     * @return string File size in KB (e.g., "123.45 KB") or "N/A" if not found
     */
    public function getProcessedArtifactSizeKB(): string
    {
        $artifact = $this->artifacts->first();
        
        if (!$artifact || !$artifact->url) {
            return 'N/A';
        }

        $storageService = new \App\Services\Storage\ReleaseStorageService();
        $fullPath = $storageService->getFullPath($artifact->url);
        
        if (!file_exists($fullPath)) {
            return 'N/A';
        }

        $sizeBytes = filesize($fullPath);
        $sizeKB = round($sizeBytes / 1024, 2);
        
        return number_format($sizeKB, 2) . ' KB';
    }
}

