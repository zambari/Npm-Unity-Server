@include('common.topmenu')

<main class="container">
<div class="{{ config('app.main_div_style') }}">
        <div class="mb-4">
            <a href="{{ route('packages.index') }}" class="btn btn-outline-secondary">← Back to Packages</a>
        </div>

        <h1>Create New Package</h1>
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

        <form method="POST" action="{{ route('packages.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="scope_id" class="form-label">Scope/{{config('app.scope_label')}}</label>
                <select class="form-select" id="scope_id" name="scope_id">
                    <option value="">— Please Select —</option>
                    @foreach($scopes as $scope)
                        <option value="{{ $scope->id }}" data-scope="{{ $scope->scope }}" {{ old('scope_id') == $scope->id ? 'selected' : '' }}>
                            {{ $scope->display_name ? $scope->display_name . ' (' . $scope->scope . ')' : $scope->scope }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label for="bundle_id" class="form-label">Bundle ID <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="bundle_id" name="bundle_id" 
                       value="{{ old('bundle_id') }}" required maxlength="45" 
                       placeholder="com.example.mypackage">
                <div class="form-text">Unique package identifier (e.g., com.example.mypackage). 
                @if(!config('app.enable_bundle_editing'))    
                Cannot be changed after creation.</div>
                @endif
                <div id="bundle_id_validation" class="text-danger small mt-1" style="display: none;"></div>
            </div>

            <div class="mb-3">
                <label for="product_name" class="form-label">Product Name</label>
                <input type="text" class="form-control" id="product_name" name="product_name" 
                       value="{{ old('product_name') }}" maxlength="45" 
                       placeholder="My Awesome Package">
                <div class="form-text">Display name shown in Unity Package Manager</div>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" 
                          rows="3" maxlength="255">{{ old('description') }}</textarea>
            </div>

            <div class="mb-3">
                <label for="repository-url" class="form-label">Repository URL</label>
                <input type="url" class="form-control" id="repository-url" name="repository-url" 
                       value="{{ old('repository-url') }}" maxlength="500" 
                       placeholder="https://github.com/example/repo">
                <div class="form-text">Optional: URL to the package's repository</div>
            </div>

            <div class="mb-3">
                <label for="homepage-url" class="form-label">Homepage URL</label>
                <input type="url" class="form-control" id="homepage-url" name="homepage-url" 
                       value="{{ old('homepage-url') }}" maxlength="500" 
                       placeholder="https://example.com">
                <div class="form-text">Optional: URL to the package's homepage</div>
            </div>

    
@if (config('app.use_feature_publish_status'))
            <div class="mb-3">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select class="form-select" id="status" name="status" required>
                    @foreach(\App\Enums\PackageStatus::all() as $value => $label)
                        <option value="{{ $value }}" {{ old('status', \App\Enums\PackageStatus::PUBLISHED) == $value ? 'selected' : '' }}>
                            {{ ucfirst($label) }}
                        </option>
                    @endforeach
                </select>
            </div>
@endif
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="add_initial_release" name="add_initial_release" value="1" 
                           {{ old('add_initial_release') ? 'checked' : '' }}>
                    <label class="form-check-label" for="add_initial_release">
                        Add initial release (version 0.0.0.0, no artifact)
                    </label>
                    <div class="form-text">Creates an initial release placeholder that can be updated later with an artifact.</div>
                </div>
            </div>
            <div class="mb-3">
                <label for="readme_file" class="form-label">README.md</label>
                <input type="file" class="form-control" id="readme_file" name="readme_file" accept=".md,.txt">
                <div class="form-text">Optional: Upload a README.md file for this package. You will be able to add it to each release.</div>
            </div>
 <!---
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
-->
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create Package</button>
                <a href="{{ route('packages.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

@include('common.footer')

<script src="{{ asset('css/js/bundle-id-validator.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initBundleIdValidator({
        scopeSelectId: 'scope_id',
        bundleIdInputId: 'bundle_id',
        validationDivId: 'bundle_id_validation',
        enableAutoFill: true
    });
});
</script>

