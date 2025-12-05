@include('common.topmenu')

<main class="container">
<div class="{{ config('app.main_div_style') }}">
        <div class="mb-4">
            <a href="{{ route('packages.index') }}" class="btn btn-outline-secondary">← Back to Packages</a>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>{{ $package->product_name ?: $package->bundle_id }}</h1>
            <a href="{{ route('packages.edit', $package->bundle_id) }}" class="btn btn-primary">Edit Package</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <div class="mb-2">
                    <strong>{!! session('success') !!}</strong>
                </div>
                @if(session('release_info'))
                    @php
                        $info = session('release_info');
                    @endphp
                    <div class="small mt-2">
                        <div class="mb-1">
                            <strong>File Path:</strong> <code class="text-break">{{ $info['file_path'] ?? 'N/A' }}</code>
                        </div>
                        <div class="mb-1">
                            <strong>File Size:</strong> {{ $info['file_size'] ?? 'N/A' }}
                        </div>
                        @if(isset($info['processing_time']))
                            <div>
                                <strong>Processing Time:</strong> {{ $info['processing_time'] }} seconds
                            </div>
                        @endif
                    </div>
                @endif
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- Package Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Package Information</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">Bundle ID</dt>
                    <dd class="col-sm-9"><code>{{ $package->bundle_id }}</code></dd>

                    <dt class="col-sm-3">Product Name</dt>
                    <dd class="col-sm-9">{{ $package->product_name ?: '—' }}</dd>

                    <dt class="col-sm-3">Description</dt>
                    <dd class="col-sm-9">{{ $package->description ?: '—' }}</dd>
                    @if(config('app.use_feature_publish_status'))
                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9">
                        @php
                            $statusLabel = \App\Enums\PackageStatus::label($package->status ?? 0);
                            $badgeClass = ($package->status == \App\Enums\PackageStatus::PUBLISHED) ? 'bg-success' : 'bg-warning';
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                    </dd>
                    @endif

                    <dt class="col-sm-3">Latest Version</dt>
                    <dd class="col-sm-9">
                        @if($latestPublishedRelease)
                            <code>{{ $latestPublishedRelease->version }}</code>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </dd>

                    <dt class="col-sm-3"> {{config('app.scope_label')}}</dt>
                    <dd class="col-sm-9">
                        @if($package->scope)
                            @if($package->scope->display_name)
                                <strong>{{ $package?->scope->display_name }}</strong> <code class="text-muted">({{ $package?->scope->scope }})</code>
                            @else
                                <code>{{ $package?->scope->scope }}</code>
                            @endif
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </dd>
@if ($package?->creator?->name != null)
                    <dt class="col-sm-3">Created By</dt>
                    <dd class="col-sm-9">{{ $package->creator->name ?? $package->creator->email ?? '—' }}</dd>
@endif
                    <dt class="col-sm-3">Created At</dt>
                    <dd class="col-sm-9">{{ $package->created_at->format('Y-m-d H:i:s') }}</dd>

                    <dt class="col-sm-3">Total Downloads</dt>
                    <dd class="col-sm-9">{{ number_format($totalDownloads ?? 0) }}</dd>
                </dl>
            </div>
        </div>

        <!-- Releases Section -->
        <x-releases-list :package="$package" :showAddButton="true" :showEditColumn="false" />
    </div>
</main>

@include('common.footer')

