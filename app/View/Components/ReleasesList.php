<?php

namespace App\View\Components;

use App\Enums\ReleaseStatus;
use App\Models\Package;
use Illuminate\View\Component;

class ReleasesList extends Component
{
    public $releases;
    public $package;
    public $showAddButton;
    public $currentReleaseId;
    public $showEditColumn;

    /**
     * Create a new component instance.
     */
    public function __construct(Package $package, bool $showAddButton = true, ?int $currentReleaseId = null, bool $showEditColumn = true)
    {
        $this->package = $package;
        $this->showAddButton = $showAddButton;
        $this->currentReleaseId = $currentReleaseId;
        $this->showEditColumn = $showEditColumn;
        
        // Eager load artifacts to avoid N+1 queries
        $package->load('releases.artifacts');
        
        // Format releases for display
        $this->releases = $package->releases->map(function ($release) {
            return $this->formatReleaseForDisplay($release);
        })->sort(function ($a, $b) {
            // Sort by version using semantic version comparison (descending - newest first)
            $versionA = $a['version'] ?? '0.0.0';
            $versionB = $b['version'] ?? '0.0.0';
            return version_compare($versionB, $versionA);
        })->values();
    }

    /**
     * Format release for display
     */
    protected function formatReleaseForDisplay($release): array
    {
        $statusValue = $release->release_status ?? ReleaseStatus::UNKNOWN;
        $statusLabel = ReleaseStatus::label($statusValue);
        $statusBadge = $this->getStatusBadge($statusValue);
        
        $changelog = $release->changelog ?? null;
        $hasChangelog = !empty(trim($changelog ?? ''));
        
        return [
            'id' => $release->id,
            'version' => $release->version ?? 'N/A',
            'channel' => $release->channel ?? 'N/A',
            'status' => $statusLabel,
            'status_value' => $statusValue,
            'status_badge' => $statusBadge,
            'created_at' => $release->create_time?->format('Y-m-d H:i:s') ?? 'N/A',
            'file_size_kb' => $release->getProcessedArtifactSizeKB(),
            'changelog' => $changelog,
            'has_changelog' => $hasChangelog,
        ];
    }

    /**
     * Get Bootstrap badge class for release status
     */
    protected function getStatusBadge(?int $status): string
    {
        if ($status === null) {
            return 'bg-secondary';
        }
        
        switch ($status) {
            case ReleaseStatus::PUBLISHED:
                return 'bg-success';
            case ReleaseStatus::UNPUBLISHED:
                return 'bg-warning';
            case ReleaseStatus::UNKNOWN:
            default:
                return 'bg-secondary';
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.releases-list');
    }
}

