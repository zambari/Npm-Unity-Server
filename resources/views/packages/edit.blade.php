@include('common.topmenu')

<main class="container">
<div class="{{ config('app.main_div_style') }}">
        <div class="mb-4">
            <a href="{{ route('packages.show', $package->bundle_id) }}" class="btn btn-outline-secondary">← Back to Package</a>
        </div>

        <h1>Edit Package</h1>
        <x-read-only-warning />

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

        <form method="POST" action="{{ route('packages.update', $package->bundle_id) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label for="scope_id" class="form-label">Scope/{{config('app.scope_label')}}</label>
                <select class="form-select" id="scope_id" name="scope_id">
                    <option value="">— No Scope —</option>
                    @foreach($scopes as $scope)
                        <option value="{{ $scope->id }}" data-scope="{{ $scope->scope }}" {{ old('scope_id', $package->scope_id) == $scope->id ? 'selected' : '' }}>
                            {{ $scope->display_name ? $scope->display_name . ' (' . $scope->scope . ')' : $scope->scope }}
                        </option>

                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label for="bundle_id" class="form-label">Bundle ID</label>
                <input type="text" class="form-control" id="bundle_id" name="bundle_id" value="{{ old('bundle_id', $package->bundle_id) }}"
                @if(!config('app.enable_bundle_editing'))
                disabled
                @endif
                 >
                 @if(!config('app.enable_bundle_editing'))
                <div class="form-text">Bundle ID cannot be changed after creation.</div>
                @endif
                <div id="bundle_id_validation" class="text-danger small mt-1" style="display: none;"></div>
            </div>

            <div class="mb-3">
                <label for="product_name" class="form-label">Product Name</label>
                <input type="text" class="form-control" id="product_name" name="product_name" 
                       value="{{ old('product_name', $package->product_name) }}" maxlength="45" 
                       placeholder="My Awesome Package">
                <div class="form-text">Display name shown in Unity Package Manager</div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" 
                          rows="3" maxlength="255">{{ old('description', $package->description) }}</textarea>
            </div>

            <div class="mb-3">
                <label for="repository-url" class="form-label">Repository URL</label>
                <input type="url" class="form-control" id="repository-url" name="repository-url" 
                       value="{{ old('repository-url', $package->repository_url) }}" maxlength="500" 
                       placeholder="https://github.com/example/repo">
                <div class="form-text">Optional: URL to the package's repository</div>
            </div>

            <div class="mb-3">
                <label for="homepage-url" class="form-label">Homepage URL</label>
                <input type="url" class="form-control" id="homepage-url" name="homepage-url" 
                       value="{{ old('homepage-url', $package->homepage_url) }}" maxlength="500" 
                       placeholder="https://example.com">
                <div class="form-text">Optional: URL to the package's homepage</div>
            </div>

        
            @if(config('app.use_feature_publish_status'))
            <div class="mb-3">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select class="form-select" id="status" name="status" required>
                    @foreach(\App\Enums\PackageStatus::all() as $value => $label)
                        <option value="{{ $value }}" {{ old('status', $package->status) == $value ? 'selected' : '' }}>
                            {{ ucfirst($label) }}
                        </option>
                    @endforeach
                </select>
            </div>
@endif
@if(config('app.use_feature_channels'))
            <div class="mb-3">
                <label for="channel" class="form-label">Channel</label>
           <select class="form-select" id="channel" name="channel">
                    <option value="">— No Channel —</option>
                    @foreach(\App\Enums\Channel::all() as $channel)
                        <option value="{{ $channel }}" {{ old('channel') == $channel ? 'selected' : '' }}>
                            {{ ucfirst($channel) }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Note: Channel is stored per release, not per package. This field is for future use.</div>
</div>
@endif
            <div class="mb-3">
                <label for="readme_file" class="form-label">README.md</label>
                <input type="file" class="form-control" id="readme_file" name="readme_file" accept=".md,.txt">
                <div class="form-text">
                    Optional: Upload a README.md file for this package. You will be able to add it to each release.
                    @if(isset($readmeExists))
                        <br>
                        <span class="badge {{ $readmeExists ? 'bg-success' : 'bg-secondary' }}">
                            Current status: {{ $readmeExists ? 'File exists' : 'File does not exist' }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update Package</button>
                <a href="{{ route('packages.show', $package->bundle_id) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <!-- Releases Section -->
        <div class="mt-4 pt-4 border-top">
            <x-releases-list :package="$package" :showAddButton="true" />
        </div>
    </div>
</main>

@include('common.footer')

@if(config('app.enable_bundle_editing'))
<script src="{{ asset('css/js/bundle-id-validator.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initBundleIdValidator({
        scopeSelectId: 'scope_id',
        bundleIdInputId: 'bundle_id',
        validationDivId: 'bundle_id_validation',
        enableAutoFill: false // Don't auto-fill in edit mode
    });
});
</script>
@endif

