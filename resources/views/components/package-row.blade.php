@props([
    'package',
    'scopeId' => null,
    'allowEditing' => false,
])

@php
    $collapseId = 'collapse-package-' . $package->id;
@endphp

<div class="list-group-item">
    <div class="d-flex justify-content-between align-items-start">
        <div class="flex-grow-1">
            <div class="fw-bold mb-1">{{ $package->product_name ?: '—' }}</div>

            @if($scopeId === null && $package->scope)
                <div class="small text-muted">
                    {{ $package->scope->display_name ?: $package->scope->scope }}
                    @if($package->scope->display_name && $package->scope->scope)
                        <span class="text-muted">/</span>
                    @endif
                    @if($package->scope->display_name)
                        <code class="small">{{ $package->scope->scope }}</code>
                    @endif
                </div>
            @endif
            <div class="small text-muted mb-1">
                <code>{{ $package->bundle_id }}</code>
            </div>
            <div class="mb-1">
                @if($package->latestPublishedRelease)
                    <code>{{ $package->latestPublishedRelease->version }}</code>
                @else
                    <span class="text-muted">—</span>
                @endif
                @if($package->releases_count > 0)
                    <small class="text-muted ms-2">
                        ({{ $package->releases_count == $package->published_releases_count 
                            ? $package->releases_count 
                            : $package->published_releases_count . ' / ' . $package->releases_count }} releases / {{ $package->downloads_count ?? 0 }} downloads)
                    </small>
                @endif
            </div>
       
           
            @if(config('app.use_feature_publish_status'))
                <div class="mt-1">
                    @php
                        $statusLabel = \App\Enums\PackageStatus::label($package->status ?? 0);
                        $badgeClass = ($package->status == \App\Enums\PackageStatus::PUBLISHED) ? 'bg-success' : 'bg-warning';
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                </div>
            @endif
            
            @if($package->releases && $package->releases->count() > 0)
                <button class="btn btn-link p-0 text-start text-decoration-none mt-2 d-flex align-items-center justify-content-between w-100" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#{{ $collapseId }}" 
                        aria-expanded="false" 
                        aria-controls="{{ $collapseId }}">
                    <span>{{ $package->releases->count() }} releases</span>
                    <span class="chevron-icon ms-2">
                        <x-icon-chevron-down />
                    </span>
                </button>
            @endif
        </div>
        @if($allowEditing)
            <div class="ms-3">
                <a href="{{ route('packages.show', $package->bundle_id) }}" class="btn btn-sm btn-outline-primary">Details</a>
                <a href="{{ route('packages.edit', $package->bundle_id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
            </div>
        @endif
    </div>
    
    @if($package->releases && $package->releases->count() > 0)
        <div class="collapse mt-2" id="{{ $collapseId }}">
            <div class="ps-3 border-start border-2 border-secondary">
                <div class="small">
                    @php
                        $displayedReleases = $package->releases->take(10);
                        $totalReleases = $package->releases->count();
                        $hasMore = $totalReleases > 10;
                    @endphp
                    @foreach($displayedReleases as $release)
                        <x-release-row :release="$release" />
                    @endforeach
                    @if($hasMore)
                        <div class="text-muted text-center py-2" style="font-size: 0.9em;">
                            ... {{ $totalReleases - 10 }} more release(s)
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

<style>
    .chevron-icon {
        transition: transform 0.3s ease;
    }
    button[aria-expanded="true"] .chevron-icon {
        transform: rotate(180deg);
    }
</style>
