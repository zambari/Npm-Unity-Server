@include('common.topmenu')

<main class="container">
<div class="{{ config('app.main_div_style') }}">
        <div class="mb-4">
            <a href="{{ route('packages.show', $package->bundle_id) }}" class="btn btn-outline-secondary">← Back to Package</a>
        </div>

        <h1>Create New Release</h1>
        <p class="text-muted">Package: <code>{{ $package->bundle_id }}</code></p>

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('packages.releases.store', $package->bundle_id) }}" enctype="multipart/form-data">
            @csrf
       
            <div class="mb-3">
                <label for="version" class="form-label">
                    Version <span class="text-danger">*</span>
                    @if($package->latestPublishedVersion())
                        <small class="text-muted">(Latest published: <code>{{ $package->latestPublishedVersion() }}</code>)</small>
                    @endif
                </label>
                <input type="text" class="form-control" id="version" name="version" 
                       value="{{ old('version', $package->latestPublishedVersion() ?? '') }}" required maxlength="45" 
                       placeholder="1.0.0"
                       data-current-highest-version="{{ $package->latestPublishedVersion() ?? '' }}">
                <div class="form-text">Release version (e.g., 1.0.0, 2.1.3). Must be higher than the current highest version.</div>
            </div>
            @if(config('app.use_feature_channels'))
            <div class="mb-3">
                <label for="channel" class="form-label">Channel</label>
                <select class="form-select" id="channel" name="channel">
                    @foreach(\App\Enums\Channel::all() as $channel)
                        <option value="{{ $channel }}" {{ old('channel', \App\Enums\Channel::PUBLIC) == $channel ? 'selected' : '' }}>
                            {{ ucfirst($channel) }}
                        </option>
                    @endforeach
                </select>
            </div>
@endif

            <div class="mb-3">
                <label for="changelog" class="form-label">Changelog</label>
                <textarea class="form-control" id="changelog" name="changelog" rows="6" 
                          placeholder="Enter changelog information...">{{ old('changelog') }}</textarea>
                <div class="form-text">Release changelog and notes (will be automatically formatted)</div>
            </div>

            <!-- References Section -->
            <x-release-references 
                :package="$package"
                :isFirstRelease="$isFirstRelease"
                :ancestorRelease="$ancestorRelease"
                :hasAncestorReferences="$hasAncestorReferences"
                :ancestorReferencesUrl="$latestRelease ? route('packages.releases.ancestor-references', ['package' => $package->bundle_id, 'release' => $latestRelease->id]) : null"
            />

            <div class="mb-3">
                <small class="text-muted">
                    <strong>Note:</strong> You are expected to upload a zip file (or .unitypackage) with proper Unity package folder structure 
                    (<a href="https://docs.unity3d.com/2019.1/Documentation/Manual/cus-layout.html" target="_blank" rel="noopener noreferrer">see Unity package layout documentation</a>). 
                    The <code>package.json</code> file will be automatically generated and added to the root of your package during processing.
                </small>
            </div>

            <div class="mb-3">
                <label for="artifact" class="form-label">Package for processing: <span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="artifact" name="artifact" required>
                <div class="form-text">Upload the release artifact file (max 100MB). Files are stored in incoming/{{ $package->bundle_id }}/[date]/</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create Release</button>
                <a href="{{ route('packages.show', $package->bundle_id) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <!-- Releases Section -->
        <div class="mt-4 pt-4 border-top">
            <x-releases-list :package="$package" :showAddButton="false" />
        </div>
    </div>
</main>

@include('common.footer')

<script src="{{ asset('css/js/version-validator.js') }}"></script>

