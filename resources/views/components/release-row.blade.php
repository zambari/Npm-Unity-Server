@props([
    'release',
])


    <div class="fw-semibold mb-1">
        <code>{{ $release->version }}</code> 
        <span class="text-muted ms-1">({{ $release?->create_time->format('Y-m-d H:i') }})</span>
    

        @if (config('app.use_feature_channels') && $release->channel)
            <span class="badge bg-secondary ms-1">{{ $release->channel }}</span>
        @endif
        
        @php
            $statusLabel = \App\Enums\ReleaseStatus::label($release->release_status ?? 0);
            $statusBadge = \App\Enums\ReleaseStatus::badge($release->release_status ?? 0);
        @endphp
        @if (config('app.use_feature_publish_status'))
        <span class="badge {{ $statusBadge }} ms-1">{{ $statusLabel }}</span>
        @endif
        
    @if($release->changelog && trim($release->changelog))
        <div class="mb-1">
            <pre class="mb-0 small" style="white-space: pre-wrap; font-family: inherit; color: var(--bs-body-color); background: transparent; border: none; padding: 0;">{{ trim($release->changelog) }}</pre>
        </div>
  
    @endif
    </div>
   