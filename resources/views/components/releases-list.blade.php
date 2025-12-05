<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
   @if($showAddButton)
        <h5 class="mb-0">Other releases</h5>
        @else
        <h5 class="mb-0">Releases</h5>
        @endif
        @if($showAddButton)
            <a href="{{ route('packages.releases.create', $package->bundle_id) }}" class="btn btn-sm btn-primary">+ Add Release</a>
        @endif
    </div>
    <div class="card-body">
       
        @if($releases->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Version</th>
                            @if(config('app.use_feature_channels'))

                            <th>Channel</th>
                            @endif
                            @if(config('app.use_feature_channels'))
                            <th>Status</th>
                            @endif
                            @if(!config('app.use_feature_channels'))
                            <th>File Size</th>
                            @endif
                            <th>Downloads</th>
                            <th>Created</th>
                            @if($showEditColumn)
                            <th>Actions</th>
                            @endif
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        
                        @foreach($releases as $release)
                            <tr data-release-id="{{ $release['id'] }}">
                                <td><code>{{ $release['version'] }}</code></td>
                                @if(config('app.use_feature_channels'))
                                <td>{{ $release['channel'] }}</td>
                                @endif
                                @if(config('app.use_feature_publish_status'))
                                <td>
                                    <span class="badge {{ $release['status_badge'] }}">{{ $release['status'] }}</span>
                                </td>
                                @endif
                                @if(!config('app.use_feature_channels'))
                            <td>{{ $release['file_size_kb'] }}</td>
                            @endif

                                <td>{{ number_format($release['download_count'] ?? 0) }}</td>
                                <td>{{ $release['created_at'] }}</td>
                                @if($showEditColumn)
                                <td>
                                    @if($currentReleaseId !== $release['id'])
                                        <a href="{{ route('packages.releases.edit', ['package' => $package->bundle_id, 'release' => $release['id']]) }}" 
                                           class="btn btn-sm btn-outline-primary">Edit</a>
                                    @else
                                        <span class="text-muted">Currently editing</span>
                                    @endif
                                </td>
                                @endif
                                <td class="text-end">
                                    @if($release['has_changelog'])
                                        <button type="button" 
                                                class="btn btn-sm btn-link p-0 changelog-toggle" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#changelog-{{ $release['id'] }}"
                                                aria-expanded="false"
                                                aria-controls="changelog-{{ $release['id'] }}"
                                                style="text-decoration: none;">
                                            <x-icon-chevron-down />
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @if($release['has_changelog'])
                            <tr>
                                @php
                                    $colspan = 1; // Version
                                    if (config('app.use_feature_channels')) {
                                        $colspan += 1; // Channel
                                        $colspan += 1; // Status (shown when use_feature_channels is true)
                                    } else {
                                        $colspan += 1; // File Size
                                    }
                                    $colspan += 1; // Downloads
                                    $colspan += 1; // Created
                                    if ($showEditColumn) {
                                        $colspan += 1; // Actions
                                    }
                                    $colspan += 1; // Chevron column (last)
                                @endphp
                                <td colspan="{{ $colspan }}" class="p-0">
                                    <div class="collapse" id="changelog-{{ $release['id'] }}">
                                        <div class="card card-body border-0" style="background-color: var(--bs-secondary-bg);">
                                            <h6 class="mb-2">Changelog:</h6>
                                            <pre class="mb-0" style="white-space: pre-wrap; font-family: inherit; font-size: 0.9em; color: var(--bs-body-color);">{{ $release['changelog'] }}</pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No releases yet for this package.</p>
        @endif
    </div>
</div>

<script>
(function() {
    // Handle chevron rotation on collapse toggle
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.changelog-toggle').forEach(function(button) {
            const targetId = button.getAttribute('data-bs-target');
            const targetElement = document.querySelector(targetId);
            const svg = button.querySelector('svg');
            
            if (targetElement && svg) {
                // Set initial transition
                svg.style.transition = 'transform 0.2s ease';
                
                targetElement.addEventListener('shown.bs.collapse', function() {
                    svg.style.transform = 'rotate(180deg)';
                });
                
                targetElement.addEventListener('hidden.bs.collapse', function() {
                    svg.style.transform = 'rotate(0deg)';
                });
            }
        });
    });
})();
</script>

