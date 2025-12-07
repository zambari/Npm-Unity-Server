@include('common.topmenu')

<main class="container">
    <div class={{config('app.main_div_style')}}>

        <h1 class="mb-4">Experimental / Tools</h1>




        <x-collapsible-card name="Storage Statistics" id="storageStatsCollapse" :expanded="false">
            <div class="card-body">
                <!-- Incoming Directory Stats -->
                <h5 class="mb-3">Incoming Directory (storage/app/private/incoming)</h5>
                <div class="alert alert-info mb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Total:</strong> {{ $storageStats['incoming']['total_files'] }} files,
                        {{ number_format($storageStats['incoming']['total_size_kb'], 2) }} KB
                    </div>
                    @if(count($storageStats['incoming']['packages']) > 0)
                    <form method="POST" action="{{ route('admin.databaseadmin.delete-all-incoming-but-latest') }}"
                        onsubmit="return confirm('This will delete all date folders except the latest one for each package. Continue?');"
                        class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning">
                            <i class="bi bi-trash3"></i> Delete All But Latest
                        </button>
                    </form>
                    @endif
                </div>

                @if(count($storageStats['incoming']['packages']) > 0)
                <div class="table-responsive mb-4">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Bundle ID</th>
                                <th>Number of Files</th>
                                <th>Total Size (KB)</th>
                                <th>Latest Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($storageStats['incoming']['packages'] as $package)
                            <tr>
                                <td><code>{{ $package['bundle_id'] }}</code></td>
                                <td>{{ $package['file_count'] }}</td>
                                <td>{{ number_format($package['size_kb'], 2) }}</td>
                                <td>{{ $package['latest_date'] ?? 'N/A' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.databaseadmin.delete-incoming-package') }}"
                                        onsubmit="return confirm('Delete all files for package {{ $package['bundle_id'] }}? This cannot be undone!');"
                                        class="d-inline">
                                        @csrf
                                        <input type="hidden" name="bundle_id" value="{{ $package['bundle_id'] }}">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash3"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted">No files found in incoming directory.</p>
                @endif

                <hr class="my-4">

                <!-- Processed Directory Stats -->
                <h5 class="mb-3">Processed Directory (storage/app/private/incoming_processed)</h5>
                <div class="alert alert-info mb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Total:</strong> {{ $storageStats['processed']['total_files'] }} files,
                        {{ number_format($storageStats['processed']['total_size_kb'], 2) }} KB
                    </div>
                    @if(count($storageStats['processed']['files']) > 0)
                    <form method="POST" action="{{ route('admin.databaseadmin.delete-all-processed-but-latest') }}"
                        onsubmit="return confirm('This will delete all processed files except the latest one. Continue?');"
                        class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning">
                            <i class="bi bi-trash3"></i> Delete All But Latest
                        </button>
                    </form>
                    @endif
                </div>

                @if(count($storageStats['processed']['files']) > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Size (KB)</th>
                                <th>Modified</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($storageStats['processed']['files'] as $file)
                            <tr>
                                <td><code>{{ $file['filename'] }}</code></td>
                                <td>{{ number_format($file['size_kb'], 2) }}</td>
                                <td>{{ date('Y-m-d H:i:s', $file['modified_time']) }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.databaseadmin.delete-processed-file') }}"
                                        onsubmit="return confirm('Delete file {{ $file['filename'] }}? This cannot be undone!');"
                                        class="d-inline">
                                        @csrf
                                        <input type="hidden" name="filename" value="{{ $file['filename'] }}">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash3"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted">No files found in processed directory.</p>
                @endif
            </div>
        </x-collapsible-card>

        <x-collapsible-card name="Example data creator" id="exampleDataCollapse" :expanded="false">
            <div class="card-body">
                <p class="card-text mb-3">
                    Generate example data to seed the database with scopes (categories), packages, and releases.
                </p>
                <form method="POST" action="{{ route('admin.databaseadmin.create-example-data') }}"
                    onsubmit="return confirm('This will create example scopes, packages, and releases. Continue?');">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="num_categories" class="form-label">Number of Categories (x)</label>
                            <input type="number" class="form-control" id="num_categories" name="num_categories"
                                value="{{ old('num_categories', 3) }}" min="1" max="50" required>
                            <small class="form-text text-muted">Number of scopes/categories to create</small>
                        </div>
                        <div class="col-md-4">
                            <label for="num_packages" class="form-label">Number of Packages (y)</label>
                            <input type="number" class="form-control" id="num_packages" name="num_packages"
                                value="{{ old('num_packages', 6) }}" min="1" max="200" required>
                            <small class="form-text text-muted">If y ≤ x, it will be set to x × 2</small>
                        </div>
                        <div class="col-md-4">
                            <label for="num_releases" class="form-label">Number of Releases (z)</label>
                            <input type="number" class="form-control" id="num_releases" name="num_releases"
                                value="{{ old('num_releases', 3) }}" min="1" max="5" required>
                            <small class="form-text text-muted">Per package (min: 1, max: 5)</small>
                        </div>
                        <div class="col-md-12">
                            <label for="base_scope" class="form-label">Base Scope</label>
                            <input type="text" class="form-control" id="base_scope" name="base_scope"
                                value="{{ old('base_scope', config('app.default_scope')) }}"
                                pattern="[a-zA-Z0-9.]+" required>
                            <small class="form-text text-muted">Base scope prefix for generated scopes (e.g., com.example)</small>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-database-add"></i> Create Example Data
                    </button>
                </form>
            </div>
        </x-collapsible-card>

        <x-collapsible-card name="Database management" id="experimentsCollapse" :expanded="false">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Database Backup & Restore</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <p class="card-text">Download a complete SQL dump of the database.</p>
                            <a href="{{ route('admin.databaseadmin.download-dump') }}" class="btn btn-primary">
                                <i class="bi bi-download"></i> Download SQL Dump
                            </a>
                        </div>
                    </div>
                    <div>
                        <div class="col-md-4">
                            <p class="card-text">Upload and restore a SQL dump file.</p>
                            <form method="POST" action="{{ route('admin.databaseadmin.restore-dump') }}" enctype="multipart/form-data" onsubmit="return confirm('This will replace all current database data with the uploaded dump. Continue?');">
                                @csrf
                                <div class="input-group">
                                    <input type="file" class="form-control" id="sqlDump" name="sql_dump" accept=".sql,.txt" required>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-upload"></i> Restore Backup
                                    </button>
                                </div>
                                <small class="form-text text-muted">Accepted formats: .sql, .txt</small>
                            </form>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <p class="card-text">Delete all packages, releases, release artifacts, and storage. Preserves users and scopes.</p>
                            <form method="POST" action="{{ route('admin.databaseadmin.clear-packages-data') }}" onsubmit="return confirm('⚠️ WARNING: This will permanently delete ALL packages, releases, release artifacts, and storage files. Users and scopes will be preserved. This action cannot be undone! Are you sure?');">
                                @csrf
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-trash3"></i> Clear Packages & Storage
                                </button>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <p class="card-text">Delete all users, packages, scopes, releases, and release artifacts.</p>
                            <form method="POST" action="{{ route('admin.databaseadmin.clear-data') }}" onsubmit="return confirm('⚠️ WARNING: This will permanently delete ALL users, packages, scopes, releases, and release artifacts. This action cannot be undone! Are you absolutely sure?');">
                                @csrf
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-trash3"></i> Clear All
                                </button>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <p class="card-text">Drop ALL tables from the database. This will destroy the entire database structure.</p>
                            <form method="POST" action="{{ route('admin.databaseadmin.nuke-data') }}" onsubmit="return confirm('⚠️⚠️⚠️ CRITICAL WARNING: This will DROP ALL TABLES from the database, destroying the entire database structure! This action CANNOT be undone! Are you ABSOLUTELY CERTAIN?');">
                                @csrf
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-exclamation-triangle-fill"></i> NUKE
                                </button>
                            </form>
                        </div>

                    </div>
                    
                </div>

        </x-collapsible-card>

    </div>
    <div class="mt-4">
        <div class="alert alert-warning" role="alert">
            <strong>⚠️ Warning:</strong> These experimental tools modify your database. Use with caution in production environments.
        </div>
    </div>
</main>

@include('common.footer')