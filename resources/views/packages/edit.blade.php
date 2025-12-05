@include('common.topmenu')

<main class="container">
<div class="{{ config('app.main_div_style') }}">
        <div class="mb-4">
            <a href="{{ route('packages.show', $package->bundle_id) }}" class="btn btn-outline-secondary">← Back to Package</a>
        </div>

        <h1>Edit Package</h1>

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

        <form method="POST" action="{{ route('packages.update', $package->bundle_id) }}">
            @csrf
            @method('PUT')
            
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
                <label for="scope_id" class="form-label">Scope</label>
                <select class="form-select" id="scope_id" name="scope_id">
                    <option value="">— No Scope —</option>
                    @foreach($scopes as $scope)
                        <option value="{{ $scope->id }}" {{ old('scope_id', $package->scope_id) == $scope->id ? 'selected' : '' }}>
                            {{ $scope->display_name ? $scope->display_name . ' (' . $scope->scope . ')' : $scope->scope }}
                        </option>

                    @endforeach
                </select>
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

