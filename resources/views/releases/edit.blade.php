@include('common.topmenu')

<main class="container">
    <div class="{{ config('app.main_div_style') }}">
        <div class="mb-4">
            <a href="{{ route('packages.show', $package->bundle_id) }}" class="btn btn-outline-secondary">← Back to Package</a>
        </div>

        <h1>Edit Release</h1>
        <x-read-only-warning />

        <p class="text-muted">Package: <code>{{ $package->bundle_id }}</code><br>
        Released at: <code>{{ $release->create_time ? $release->create_time->format('Y-m-d H:i:s') : 'N/A' }}</code><br>
        Released as: <code>{{ $release->version }}</code></p>
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

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

        <form method="POST" action="{{ route('packages.releases.update', ['package' => $package->bundle_id, 'release' => $release->id]) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="version" class="form-label">Version <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="version" name="version"
                    value="{{ old('version', $release->version) }}" required maxlength="45"
                    placeholder="1.0.0.0">
                <div class="form-text">Release version (e.g., 1.0.0, 2.1.3)</div>
            </div>
            @if(config('app.use_feature_channels'))
            <div class="mb-3">
                <label for="channel" class="form-label">Channel</label>
                <select class="form-select" id="channel" name="channel">
                    <option value="">None</option>
                    @foreach(\App\Enums\Channel::all() as $channel)
                    <option value="{{ $channel }}" {{ old('channel', $release->channel) == $channel ? 'selected' : '' }}>
                        {{ ucfirst($channel) }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif
            @if(config('app.use_feature_publish_status'))
            <div class="mb-3">
                <label for="release_status" class="form-label">Status <span class="text-danger">*</span></label>
                <select class="form-select" id="release_status" name="release_status" required>
                    @foreach(\App\Enums\ReleaseStatus::all() as $value => $label)
                    <option value="{{ $value }}"
                        {{ old('release_status', $release->release_status ?? \App\Enums\ReleaseStatus::UNKNOWN) == $value ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="mb-3">
                <label for="changelog" class="form-label">Changelog</label>
                <textarea class="form-control" id="changelog" name="changelog" rows="6" 
                          placeholder="Enter changelog information...">{{ old('changelog', $release->changelog) }}</textarea>
                <div class="form-text">Release changelog and notes</div>
            </div>

            <!-- References Section -->
            <x-release-references 
                :package="$package"
                :isFirstRelease="$isFirstRelease"
                :isOldestRelease="$isOldestRelease"
                :ancestorRelease="$ancestorRelease"
                :hasAncestorReferences="$hasAncestorReferences"
                :release="$release"
                :ancestorReferencesUrl="route('packages.releases.ancestor-references', ['package' => $package->bundle_id, 'release' => $release->id])"
            />

            @if($release->artifacts->isNotEmpty() && $artifactInfo)
            @php
            $artifact = $artifactInfo['artifact'] ?? $release->artifacts->first();
            @endphp
             
            <!-- Files Section (Collapsed) -->
            <x-collapsible-card name="Files / Downloads" id="filesCollapse">
                <div class="card-body">
                      
                     
                        <!-- Uploaded File Section -->

                        <div class="mb-4 mt-2 p-3 border rounded">
                            <h6 class="border-bottom pb-2 mb-3">Uploaded File</h6>
                            
                            <div class="mb-3">
                                <label for="upload_name" class="form-label">Original Filename</label>
                                <input type="text" class="form-control" id="upload_name" name="upload_name" 
                                       value="{{ old('upload_name', $artifact->upload_name) }}" maxlength="255"
                                       placeholder="Original filename">
                                <div class="form-text">The original filename when uploaded</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Upload Date</label>
                                <div>
                                    {{ $artifact->upload_date ? $artifact->upload_date->format('Y-m-d H:i:s') : 'N/A' }}
                                </div>
                            </div>

                            @if(isset($artifactInfo['uploaded_path']) && $artifactInfo['uploaded_path'])
                               
                                <div class="mb-3">
                                    <label class="form-label">File Size</label>
                                    <div>
                                        @if(isset($artifactInfo['uploaded_exists']) && $artifactInfo['uploaded_exists'])
                                            <strong>{{ number_format($artifactInfo['uploaded_size_kb'], 2) }} KB</strong>
                                            <small class="text-muted">({{ number_format($artifactInfo['uploaded_size_bytes']) }} bytes)</small>
                                        @else
                                            <span class="text-danger">File not found</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Checksum (SHA1)</label>
                                    <div>
                                        @if($artifact->shasum)
                                            <code class="text-break">{{ $artifact->shasum }}</code>
                                        @else
                                            <span class="text-muted">Not calculated</span>
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    @if(isset($artifactInfo['uploaded_exists']) && $artifactInfo['uploaded_exists'])
                                        <a href="{{ route('packages.releases.download', ['package' => $package->bundle_id, 'release' => $release->id]) }}" 
                                           class="btn btn-primary btn-sm">
                                            <x-icon-download />
                                            Download Uploaded File
                                        </a>
                                    @else
                                        <button type="button" class="btn btn-secondary btn-sm" disabled>
                                            File Not Available
                                        </button>
                                    @endif
                                  
                                  @if(isset($artifactInfo['uploaded_full_path']) && $artifactInfo['uploaded_full_path'])
                                      <div class="mt-1">
                                          <small class="text-muted">Full path:<br> <code class="text-break">{{ $artifactInfo['uploaded_full_path'] }}</code></small>
                                      </div>
                                  @endif
                              </div>

                                </div>
                            @else
                                <div class="alert alert-info">
                                    <small>Original uploaded file path not available (may have been deleted after processing).</small>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Reprocess Buttons -->
                        @if(isset($artifactInfo['uploaded_exists']) && $artifactInfo['uploaded_exists'])
                        <div class="mb-4 p-3 border rounded">
                            <h6 class="border-bottom pb-2 mb-3">Reprocess Release</h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <form method="POST" action="{{ route('packages.releases.reprocess', ['package' => $package->bundle_id, 'release' => $release->id]) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Are you sure you want to reprocess this release? This will replace the current processed artifact.')">
                                            <x-icon-download />
                                            Reprocess In Place
                                        </button>
                                    </form>
                                    <small class="text-muted d-block mt-1">Reprocess the original uploaded file and replace the current artifact</small>
                                </div>
                                <div class="col-md-6">
                                    @php
                                        $parts = explode('.', $release->version);
                                        $bumpedVersion = count($parts) >= 3 
                                            ? $parts[0] . '.' . $parts[1] . '.' . ((int)$parts[2] + 1)
                                            : 'N/A';
                                    @endphp
                                    <form method="POST" action="{{ route('packages.releases.reprocess-new-version', ['package' => $package->bundle_id, 'release' => $release->id]) }}" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Are you sure you want to create a new release? This will create a new release with a bumped patch version ({{ $release->version }} → {{ $bumpedVersion }}).')">
                                            <x-icon-plus />
                                            Reprocess & Create New Release
                                        </button>
                                    </form>
                                    <small class="text-muted d-block mt-1">Reprocess and create a new release with bumped patch version</small>
                                </div>
                            </div>
                        </div>
                        @endif
                        
                           <!-- Processed Archive Section -->
                           <div class="p-3 border rounded">
                            <h6 class="border-bottom pb-2 mb-3">Processed Archive</h6>
                            
                            <div class="mb-3">
                                <label for="processed_filename" class="form-label">Processed Filename <span class="text-danger">*<br></span><small class="text-muted">({{ $artifactInfo['processed_filename']  }})</small></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="processed_filename" name="processed_filename" 
                                           value="{{ old('processed_filename', $artifactInfo['processed_filename'] ?? '') }}" 
                                           required maxlength="255" pattern="[a-zA-Z0-9._-]+\.(tgz|tar\.gz)"
                                           placeholder="package-version-date.tgz">
                                    <span id="filename-status" class="input-group-text" style="min-width: 100px; font-size: 0.75rem;"></span>
                                </div>
                                <div class="form-text">The filename of the processed archive (must end with .tgz or .tar.gz)</div>
                            </div>


                            <div class="mb-3">
                                <label class="form-label">File Size</label>
                                <div>
                                    @if(isset($artifactInfo['processed_exists']) && $artifactInfo['processed_exists'])
                                        <strong>{{ number_format($artifactInfo['processed_size_kb'], 2) }} KB</strong>
                                        <small class="text-muted">({{ number_format($artifactInfo['processed_size_bytes']) }} bytes)</small>
                                    @else
                                        <span class="text-danger">File not found</span>
                                    @endif
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Checksum (SHA1)</label>
                                <div>
                                    @if($artifact->shasum)
                                        <code class="text-break">{{ $artifact->shasum }}</code>
                                    @else
                                        <span class="text-muted">Not calculated</span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                @if(isset($artifactInfo['processed_exists']) && $artifactInfo['processed_exists'])
                                    @php
                                        // Generate filename in the same format Unity uses: bundle_id-version-date.tgz
                                        $artifactFilename = $artifactInfo['processed_filename'] ?? basename($artifactInfo['processed_path'] ?? '');
                                        
                                        // If filename already matches our format, use it directly
                                        // Otherwise, construct it from bundle_id, version, and date
                                        if (preg_match('/^[a-zA-Z0-9._-]+-[a-zA-Z0-9._-]+-[0-9]{4}-[0-9]{2}-[0-9]{2}\.tgz$/', $artifactFilename)) {
                                            $npmFilename = $artifactFilename;
                                        } else {
                                            // Extract date from filename if possible, otherwise use release creation date
                                            $date = $release->create_time ? $release->create_time->format('Y-m-d') : date('Y-m-d');
                                            if (preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2})\.tgz$/', $artifactFilename, $matches)) {
                                                $date = $matches[1];
                                            }
                                            $safeDate = preg_replace('/[^a-zA-Z0-9._-]/', '-', $date);
                                            $safeBundleId = preg_replace('/[^a-zA-Z0-9._-]/', '_', $package->bundle_id);
                                            $safeVersion = preg_replace('/[^a-zA-Z0-9._-]/', '_', $release->version);
                                            
                                            // Construct filename: bundle_id-version-date.tgz
                                            $npmFilename = "{$safeBundleId}-{$safeVersion}-{$safeDate}.tgz";
                                        }
                                    @endphp
                                    <a href="{{ route('package.tarball', ['packageName' => $package->bundle_id, 'filename' => $npmFilename]) }}" 
                                       class="btn btn-primary btn-sm">
                                        <x-icon-download />
                                        Download Processed Archive
                                    </a>
                                @else
                                    <button type="button" class="btn btn-secondary btn-sm" disabled>
                                        File Not Available
                                    </button>
                                @endif
                                
                            <div class="mb-3">
                            
                            @if(isset($artifactInfo['processed_full_path']) && $artifactInfo['processed_full_path'])
                                <div class="mt-1">
                                    <small class="text-muted">Full path: <br><code class="text-break">{{ $artifactInfo['processed_full_path'] }}</code></small>
                                </div>
                            @endif
                        </div>
                            </div>
                            
                            @if(isset($artifactInfo['processed_exists']) && $artifactInfo['processed_exists'])
                            <!-- Tarball Inspector -->
                            <x-collapsible-card name="Inspect Tarball Structure" id="tarballInspectorCollapse" :expanded="false">
                                <x-tarball-inspector :package="$package" :release="$release" />
                            </x-collapsible-card>
                            @endif
                        </div>

                </div>
            </x-collapsible-card>
            @else
            <div class="alert alert-warning">
                No artifact found for this release.
            </div>
            @endif

            <div class="d-flex gap-2 justify-content-between align-items-center">
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteReleaseModal">
                    Delete Release
                </button>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Update Release</button>
                    <a href="{{ route('packages.show', $package->bundle_id) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
    </form>

    <!-- Releases Section -->
    <div class="mt-4 pt-4 border-top">
        <x-releases-list :package="$package" :showAddButton="true" :currentReleaseId="$release->id" />
    </div>

    </div>
</main>

<!-- Delete Release Confirmation Modal -->
<div class="modal fade" id="deleteReleaseModal" tabindex="-1" aria-labelledby="deleteReleaseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteReleaseModalLabel">Confirm Delete Release</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete release <strong>{{ $release->version }}</strong>?</p>
                <p class="text-danger"><strong>This action cannot be undone.</strong> This will permanently delete:</p>
                <ul>
                    <li>The release record</li>
                    <li>All associated artifacts and files</li>
                    <li>All dependencies and references</li>
                    <li>All download history</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="{{ route('packages.releases.destroy', ['package' => $package->bundle_id, 'release' => $release->id]) }}" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete Release</button>
                </form>
            </div>
        </div>
    </div>
</div>

@include('common.footer')

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Real-time filename validation
    const filenameInput = document.getElementById('processed_filename');
    const filenameStatus = document.getElementById('filename-status');
    let checkTimeout;
    
    if (filenameInput && filenameStatus) {
        filenameInput.addEventListener('input', function() {
            const filename = this.value.trim();
            
            // Clear previous timeout
            clearTimeout(checkTimeout);
            
            // Clear status if empty
            if (!filename) {
                filenameStatus.textContent = '';
                filenameStatus.className = 'input-group-text';
                return;
            }
            
            // Validate pattern first
            const pattern = /^[a-zA-Z0-9._-]+\.(tgz|tar\.gz)$/i;
            if (!pattern.test(filename)) {
                filenameStatus.textContent = '';
                filenameStatus.className = 'input-group-text';
                return;
            }
            
            // Debounce the check
            checkTimeout = setTimeout(function() {
                filenameStatus.textContent = 'Checking...';
                filenameStatus.className = 'input-group-text text-muted';
                
                const checkUrl = '{{ route("packages.releases.check-filename", ["package" => $package->bundle_id, "release" => $release->id]) }}';
                fetch(checkUrl + '?filename=' + encodeURIComponent(filename))
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            filenameStatus.textContent = '✓ File exists';
                            filenameStatus.className = 'input-group-text text-success bg-light';
                        } else {
                            filenameStatus.textContent = '✗ File not found';
                            filenameStatus.className = 'input-group-text text-danger bg-light';
                        }
                    })
                    .catch(error => {
                        filenameStatus.textContent = '';
                        filenameStatus.className = 'input-group-text';
                    });
            }, 500); // Wait 500ms after user stops typing
        });
        
        // Check on page load if there's a value
        if (filenameInput.value) {
            filenameInput.dispatchEvent(new Event('input'));
        }
    }
});
</script>