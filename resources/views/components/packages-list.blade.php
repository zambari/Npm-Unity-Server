@props([
    'scopeId' => null,
    'allowEditing' => false,
    'packages' => null, // Optional: pass packages collection directly
])

@php
    // If packages are not provided, query them based on scope_id filter
    if ($packages === null) {
        $query = \App\Models\Package::with(['scope', 'latestPublishedRelease', 'releases' => function($q) {
                $q->orderBy('create_time', 'desc');
            }])
            ->withCount('releases')
            ->withCount(['releases as published_releases_count' => function ($query) {
                $query->where(function ($q) {
                    $q->where('release_status', \App\Enums\ReleaseStatus::PUBLISHED)
                      ->orWhereNull('release_status'); // Treat NULL as published (default)
                });
            }])
            ->addSelect([
                'downloads_count' => \App\Models\DownloadHistory::selectRaw('count(*)')
                    ->join('releases', 'download_history.release_id', '=', 'releases.id')
                    ->whereColumn('releases.package_id', 'packages.id')
            ]);
        
        if ($scopeId !== null) {
            $query->where('scope_id', $scopeId);
        }
        
        $packages = $query->orderBy('created_at', 'desc')->get();
    }
@endphp

@if($packages->count() > 0)
    <div class="{{ $scopeId !== null ? 'mt-3' : '' }}">
        @if($scopeId !== null)
            <h5 class="mb-3">Packages ({{ $packages->count() }})</h5>
        @endif
        <div class="list-group">
            @foreach($packages as $package)
                <x-package-row 
                    :package="$package" 
                    :scopeId="$scopeId" 
                    :allowEditing="$allowEditing" 
                />
            @endforeach
        </div>
    </div>
@else
    <div class="mt-3">
        <p class="text-muted">No packages found{{ $scopeId !== null ? ' for this scope' : '' }}.</p>
    </div>
@endif
