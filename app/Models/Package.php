<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Package extends Model
{
    protected $fillable = [
        'bundle_id',
        'product_name',
        'description',
        'status',
        'disabled',
        'created_by',
        'scope_id',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'bundle_id';
    }

    /**
     * Get the user who created this package.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all releases for this package.
     */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    /**
     * Get the latest published release for this package.
     */
    public function latestPublishedRelease(): HasOne
    {
        return $this->hasOne(Release::class)
            ->where(function ($query) {
                $query->where('release_status', \App\Enums\ReleaseStatus::PUBLISHED)
                      ->orWhereNull('release_status'); // Treat NULL as published (default)
            })
            ->orderBy('create_time', 'desc')
            ->latest('create_time');
    }

    /**
     * Get the scope for this package.
     */
    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class, 'scope_id');
    }

    /**
     * Get the latest version string for this package.
     * 
     * @param int|null $releaseStatus Filter by release status (default: PUBLISHED)
     * @param string|null $channel Filter by channel (optional)
     * @param bool $useSemanticVersioning If true, sorts by semantic version; otherwise by create_time
     * @return string|null The version string (e.g., "1.2.3") or null if no matching release found
     */
    public function latestVersion(?int $releaseStatus = \App\Enums\ReleaseStatus::PUBLISHED, ?string $channel = null, bool $useSemanticVersioning = false): ?string
    {
        $query = $this->releases();
        
        // Filter by release status
        if ($releaseStatus !== null) {
            if ($releaseStatus === \App\Enums\ReleaseStatus::PUBLISHED) {
                // PUBLISHED includes NULL (default/legacy published)
                $query->where(function ($q) {
                    $q->where('release_status', \App\Enums\ReleaseStatus::PUBLISHED)
                      ->orWhereNull('release_status');
                });
            } else {
                $query->where('release_status', $releaseStatus);
            }
        }
        
        // Filter by channel if provided
        if ($channel !== null) {
            $query->where('channel', $channel);
        }
        
        // Get all matching releases
        $releases = $query->get();
        
        if ($releases->isEmpty()) {
            return null;
        }
        
        // Sort and get latest
        if ($useSemanticVersioning) {
            // Sort by semantic version (highest first)
            $sorted = $releases->sort(function ($a, $b) {
                return version_compare($b->version, $a->version);
            });
            return $sorted->first()->version;
        } else {
            // Sort by create_time (most recent first)
            return $releases->sortByDesc('create_time')->first()->version;
        }
    }

    /**
     * Get the latest published version string using semantic versioning.
     * Convenience method for the most common use case.
     * 
     * @return string|null The version string or null if no published release found
     */
    public function latestPublishedVersion(): ?string
    {
        return $this->latestVersion(\App\Enums\ReleaseStatus::PUBLISHED, null, true);
    }

    /**
     * Find the first ancestor release (going backwards in history) that has references/dependencies.
     * Starts from the given release and iterates backwards through releases ordered by create_time.
     * 
     * @param Release $fromRelease The release to start searching from
     * @return Release|null The first release with dependencies, or null if none found
     */
    public function findAncestorReleaseWithReferences(Release $fromRelease): ?Release
    {
        // Get all releases for this package, ordered by create_time descending (newest first)
        $releases = $this->releases()
            ->orderBy('create_time', 'desc')
            ->with('dependencies')
            ->get();

        // Find the index of the current release
        $currentIndex = $releases->search(function ($release) use ($fromRelease) {
            return $release->id === $fromRelease->id;
        });

        if ($currentIndex === false) {
            return null;
        }

        // Start from the release before the current one and go backwards
        for ($i = $currentIndex + 1; $i < $releases->count(); $i++) {
            $release = $releases[$i];
            // Check if this release has any dependencies with bundle_id set
            if ($release->dependencies && $release->dependencies->isNotEmpty()) {
                $hasReferences = $release->dependencies->contains(function ($dep) {
                    return !empty($dep->bundle_id);
                });
                if ($hasReferences) {
                    return $release;
                }
            }
        }

        return null;
    }
}

