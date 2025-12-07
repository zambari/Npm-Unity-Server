<?php

namespace App\Http\Controllers;

use App\Enums\Channel;
use App\Enums\PackageStatus;
use App\Enums\ReleaseStatus;
use App\Models\DownloadHistory;
use App\Models\Package;
use App\Models\PackageDependency;
use App\Models\Release;
use App\Models\ReleaseArtifact;
use App\Models\Scope;
use App\Services\Storage\ReleaseStorageService;
use App\Services\Storage\ReleaseProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PackageListViewController extends Controller
{
    /**
     * Display a listing of packages
     */
    public function index()
    {
        $packages = Package::with(['scope', 'creator', 'latestPublishedRelease', 'releases' => function($q) {
                $q->orderBy('create_time', 'desc');
            }])
            ->withCount('releases')
            ->withCount(['releases as published_releases_count' => function ($query) {
                $query->where(function ($q) {
                    $q->where('release_status', \App\Enums\ReleaseStatus::PUBLISHED)
                      ->orWhereNull('release_status'); // Treat NULL as published (default)
                });
            }])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('packages.index', compact('packages'));
    }

    /**
     * Show the form for creating a new package
     */
    public function create()
    {
        $scopes = Scope::orderBy('scope', 'asc')->get();
        return view('packages.create', compact('scopes'));
    }

    /**
     * Store a newly created package
     */
    public function store(Request $request)
    {
        $request->validate([
            'bundle_id' => [
                'required',
                'string',
                'max:45',
                'unique:packages,bundle_id',
                'regex:/^[a-z0-9][a-z0-9._-]*[a-z0-9]$/i',
            ],
            'product_name' => 'nullable|string|max:45',
            'description' => 'nullable|string|max:255',
            'scope_id' => 'nullable|exists:scopes,id'//,
            // 'status' => 'required|integer|in:' . implode(',', array_keys(PackageStatus::all())),
        ], [
            'bundle_id.required' => 'Bundle ID is required.',
            'bundle_id.unique' => 'This bundle ID already exists.',
            'bundle_id.regex' => 'Bundle ID must be a valid package identifier (e.g., com.example.mypackage).',
            // 'status.required' => 'Status is required.',
            'status.in' => 'Invalid status value.',
        ]);

        $package = new Package();
        $package->bundle_id = trim($request->bundle_id);
        $package->product_name = $request->product_name ? trim($request->product_name) : null;
        $package->description = $request->description ? trim($request->description) : null;
        $package->status = $request->status;
        $package->scope_id = $request->scope_id;
        $package->created_by = Auth::id();
        $package->save();

        // Create initial release if requested
        if ($request->has('add_initial_release') && $request->add_initial_release) {
            $release = new Release();
            $release->package_id = $package->id;
            $release->version = '0.0.0';
            $release->channel = null;
            $release->release_status = \App\Enums\ReleaseStatus::default();
            $release->user_id = Auth::id();
            $release->save();
            
            Log::info('Initial release created with package', [
                'package_id' => $package->id,
                'bundle_id' => $package->bundle_id,
                'release_id' => $release->id,
                'version' => $release->version,
                'user_id' => Auth::id(),
            ]);
        }

        $successMessage = $request->has('add_initial_release') && $request->add_initial_release
            ? 'Package created successfully with initial release (0.0.0).'
            : 'Package created successfully.';

        return redirect()->route('packages.show', $package->bundle_id)
            ->with('success', $successMessage);
    }

    /**
     * Display the specified package
     */
    public function show(Package $package)
    {
        $package->load('scope', 'creator', 'releases.user');
        
        // Load the latest published release
        $latestPublishedRelease = Release::where('package_id', $package->id)
            ->where(function ($query) {
                $query->where('release_status', \App\Enums\ReleaseStatus::PUBLISHED)
                      ->orWhereNull('release_status'); // Treat NULL as published (default)
            })
            ->orderBy('create_time', 'desc')
            ->first();
        
        // Calculate total downloads for the package (across all releases)
        $totalDownloads = \App\Models\DownloadHistory::whereHas('release', function ($query) use ($package) {
            $query->where('package_id', $package->id);
        })->count();
        
        $releases = $package->releases->map(function ($release) {
            return $this->formatReleaseForDisplay($release);
        });

        return view('packages.show', compact('package', 'releases', 'latestPublishedRelease', 'totalDownloads'));
    }

    /**
     * Show the form for editing the specified package
     */
    public function edit(Package $package)
    {
        $package->load('scope', 'creator', 'releases.user');
        $scopes = Scope::orderBy('scope', 'asc')->get();
        return view('packages.edit', compact('package', 'scopes'));
    }

    /**
     * Update the specified package
     */
    public function update(Request $request, Package $package)
    {
        $validationRules = [
            'product_name' => 'nullable|string|max:45',
            'description' => 'nullable|string|max:255',
            'status' => 'nullable|integer|in:' . implode(',', array_keys(PackageStatus::all())),
            'scope_id' => 'nullable|exists:scopes,id',
        ];

        $validationMessages = [
            'status.in' => 'Invalid status value.',
        ];

        // Only validate bundle_id if bundle editing is enabled
        if (config('app.enable_bundle_editing')) {
            $validationRules['bundle_id'] = [
                'required',
                'string',
                'max:45',
                'unique:packages,bundle_id,' . $package->id,
                'regex:/^[a-z0-9][a-z0-9._-]*[a-z0-9]$/i',
            ];
            $validationMessages['bundle_id.required'] = 'Bundle ID is required.';
            $validationMessages['bundle_id.unique'] = 'This bundle ID already exists.';
            $validationMessages['bundle_id.regex'] = 'Bundle ID must be a valid package identifier (e.g., com.example.mypackage).';
        }

        $request->validate($validationRules, $validationMessages);

        // Update bundle_id if editing is enabled and it's provided
        if (config('app.enable_bundle_editing') && $request->has('bundle_id')) {
            $package->bundle_id = trim($request->bundle_id);
        }

        $package->product_name = $request->product_name ? trim($request->product_name) : null;
        $package->description = $request->description ? trim($request->description) : null;
        if ($request->has('status')) {
            $package->status = $request->status;
        }
        $package->scope_id = $request->scope_id ?: null;
        $package->save();

        return redirect()->route('packages.show', $package->bundle_id)
            ->with('success', 'Package updated successfully.');
    }

    /**
     * Show the form for creating a new release for a package
     */
    public function createRelease(Package $package)
    {
        // Check if this is the first release (no releases exist yet)
        $isFirstRelease = $package->releases()->count() === 0;
        
        // Find the latest release with references to use as ancestor
        $ancestorRelease = null;
        $hasAncestorReferences = false;
        $latestRelease = null;
        
        if (!$isFirstRelease) {
            // Get the latest release to use as a reference point
            $latestRelease = $package->releases()->orderBy('create_time', 'desc')->first();
            if ($latestRelease) {
                $ancestorRelease = $package->findAncestorReleaseWithReferences($latestRelease);
                $hasAncestorReferences = $ancestorRelease !== null;
            }
        }
        
        return view('releases.create', compact('package', 'isFirstRelease', 'ancestorRelease', 'hasAncestorReferences', 'latestRelease'));
    }

    /**
     * Store a newly created release with artifact
     */
    public function storeRelease(Request $request, Package $package)
    {
        $request->validate([
            'version' => 'required|string|max:45',
            'channel' => 'nullable|string|in:' . implode(',', Channel::all()),
            'artifact' => 'required|file|max:102400', // 100MB max
        ], [
            'version.required' => 'Version is required.',
            'channel.in' => 'Invalid channel value.',
            'artifact.required' => 'Artifact file is required.',
            'artifact.file' => 'The artifact must be a valid file.',
            'artifact.max' => 'The artifact file size must not exceed 100MB.',
        ]);

        // Log verbose message before upload starts
        $storageService = new ReleaseStorageService();
        $storageRoot = Storage::disk('local')->path('');
        
        Log::info('Starting release upload process', [
            'package_id' => $package->id,
            'bundle_id' => $package->bundle_id,
            'version' => $request->version,
            'channel' => $request->channel,
            'user_id' => Auth::id(),
            'file_name' => $request->file('artifact')->getClientOriginalName(),
            'file_size' => $request->file('artifact')->getSize(),
            'storage_root' => $storageRoot,
            'timestamp' => now()->toIso8601String(),
        ]);

        try {
            $storageService = new ReleaseStorageService();
            $file = $request->file('artifact');
            
            // Check if file is already a tarball (.tgz or .tar.gz)
            $originalName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $isTarball = in_array($extension, ['tgz']);
            $isTarGz = preg_match('/\.tar\.gz$/i', $originalName);
            
            // Store the file
            $storageInfo = $storageService->storeReleaseFile($file, $package->bundle_id);
            
            // Create the release
            $release = new Release();
            $release->package_id = $package->id;
            $release->version = trim($request->version);
            $release->channel = $request->channel ? trim($request->channel) : null;
            $release->release_status = \App\Enums\ReleaseStatus::default(); // Default to PUBLISHED
            $release->changelog = $this->processChangelog($request->changelog);
            $release->user_id = Auth::id();
            $release->save();
            
            // Create the artifact entry with initial status
            $artifact = new ReleaseArtifact();
            $artifact->release_id = $release->id;
            $artifact->upload_name = $storageInfo['filename']; // Original filename
            $artifact->upload_date = now();
            
            // If it's already a tarball, skip processing and move directly to processed location
            if ($isTarball || $isTarGz) {
                $processingStartTime = microtime(true);
                
                // Move file from incoming to incoming_processed (simplified - no bundle_id/date folders)
                $originalFilename = basename($storageInfo['path']);
                
                // Ensure .tgz extension for processed file
                $processedFilename = $originalFilename;
                if ($isTarGz || preg_match('/\.tar\.gz$/i', $originalFilename)) {
                    $processedFilename = str_replace('.tar.gz', '.tgz', $originalFilename);
                } elseif (!preg_match('/\.tgz$/i', $processedFilename)) {
                    // If not .tgz, create a proper name: bundle_id-version-date.tgz
                    $date = Carbon::now()->format('Y-m-d');
                    $safeDate = preg_replace('/[^a-zA-Z0-9._-]/', '-', $date);
                    $safeBundleId = preg_replace('/[^a-zA-Z0-9._-]/', '_', $package->bundle_id);
                    $safeVersion = preg_replace('/[^a-zA-Z0-9._-]/', '_', trim($request->version));
                    $processedFilename = "{$safeBundleId}-{$safeVersion}-{$safeDate}.tgz";
                }
                
                $finalPath = "incoming_processed/{$processedFilename}";
                
                // Read the uploaded file and store in processed location
                $uploadedFullPath = $storageService->getFullPath($storageInfo['path']);
                Storage::disk('local')->put($finalPath, file_get_contents($uploadedFullPath));
                
                // Store original uploaded file path for reprocessing
                $artifact->url_meta = $storageInfo['path'];
                
                // Keep the original uploaded file for reprocessing (don't delete it)
                // The file will remain in incoming/{bundle_id}/{date}/ for potential reprocessing
                // $storageService->deleteFile($storageInfo['path']); // Commented out to allow reprocessing
                
                // Get file size and full path
                $finalFullPath = $storageService->getFullPath($finalPath);
                
                // Calculate SHA1 hash of the tarball
                $shasum = sha1_file($finalFullPath);
                
                // Update artifact
                $artifact->url = $finalPath;
                $artifact->shasum = $shasum;
                $artifact->status = ReleaseProcessingService::STATUS_COMPLETED;
                $artifact->save();
                
                // Calculate processing time (just file move, very fast)
                $processingEndTime = microtime(true);
                $processingDuration = round($processingEndTime - $processingStartTime, 2);
                $fileSize = file_exists($finalFullPath) ? filesize($finalFullPath) : 0;
                $fileSizeFormatted = $this->formatBytes($fileSize);
                
                Log::info('Release upload completed (tarball, no processing needed)', [
                    'package_id' => $package->id,
                    'bundle_id' => $package->bundle_id,
                    'release_id' => $release->id,
                    'artifact_id' => $artifact->id,
                    'version' => $release->version,
                    'channel' => $release->channel,
                    'uploaded_file_relative' => $storageInfo['path'],
                    'uploaded_file_full_path' => $uploadedFullPath,
                    'processed_file_relative' => $finalPath,
                    'processed_file_full_path' => $finalFullPath,
                    'tarball_filename' => $processedFilename,
                    'original_filename' => $storageInfo['filename'],
                    'file_size_bytes' => $fileSize,
                    'file_size_formatted' => $fileSizeFormatted,
                    'processing_duration_seconds' => $processingDuration,
                    'user_id' => Auth::id(),
                    'timestamp' => now()->toIso8601String(),
                ]);
                
                // Generate download URL
                $downloadUrl = route('package.tarball', [
                    'packageName' => $package->bundle_id,
                    'filename' => $processedFilename
                ]);
                
                return redirect()->route('packages.show', $package->bundle_id)
                    ->with('success', "Release {$release->version} created successfully with artifact (tarball uploaded directly). Download URL: <a href=\"{$downloadUrl}\" target=\"_blank\">{$downloadUrl}</a>")
                    ->with('release_info', [
                        'version' => $release->version,
                        'file_path' => $finalFullPath,
                        'file_size' => $fileSizeFormatted,
                        'processing_time' => $processingDuration,
                        'download_url' => $downloadUrl,
                    ]);
            } else {
                // Not a tarball, process it: unpack, add package.json, create tarball
                $artifact->url = $storageInfo['path']; // Store the relative path (will be updated after processing)
                $artifact->url_meta = $storageInfo['path']; // Store original uploaded file path for reprocessing
                $artifact->status = ReleaseProcessingService::STATUS_UPLOADED;
                $artifact->save();
                
                // Track processing start time
                $processingStartTime = microtime(true);
                
                // Process the release: unpack, add package.json, create tarball
                $processingService = new ReleaseProcessingService();
                $processedInfo = $processingService->processRelease(
                    $storageInfo['path'],
                    $package,
                    $release,
                    $artifact,
                    trim($request->version)
                );
                
                // Calculate processing time
                $processingEndTime = microtime(true);
                $processingDuration = round($processingEndTime - $processingStartTime, 2);
                
                // Keep the original uploaded file for reprocessing (don't delete it)
                // The file will remain in incoming/{bundle_id}/{date}/ for potential reprocessing
                // $storageService->deleteFile($storageInfo['path']); // Commented out to allow reprocessing
                
                // Get file size and full path
                $finalFullPath = $storageService->getFullPath($processedInfo['path']);
                $fileSize = file_exists($finalFullPath) ? filesize($finalFullPath) : 0;
                $fileSizeFormatted = $this->formatBytes($fileSize);
                
                // Log verbose message after release is finalized
                $uploadedFullPath = $storageInfo['full_path'] ?? $storageService->getFullPath($storageInfo['path']);
                
                Log::info('Release upload and processing completed successfully', [
                    'package_id' => $package->id,
                    'bundle_id' => $package->bundle_id,
                    'release_id' => $release->id,
                    'artifact_id' => $artifact->id,
                    'version' => $release->version,
                    'channel' => $release->channel,
                    'uploaded_file_relative' => $storageInfo['path'],
                    'uploaded_file_full_path' => $uploadedFullPath,
                    'processed_file_relative' => $processedInfo['path'],
                    'processed_file_full_path' => $finalFullPath,
                    'tarball_filename' => $processedInfo['filename'],
                    'original_filename' => $storageInfo['filename'],
                    'file_size_bytes' => $fileSize,
                    'file_size_formatted' => $fileSizeFormatted,
                    'processing_duration_seconds' => $processingDuration,
                    'user_id' => Auth::id(),
                    'timestamp' => now()->toIso8601String(),
                ]);
                
                // Generate download URL
                $downloadUrl = route('package.tarball', [
                    'packageName' => $package->bundle_id,
                    'filename' => $processedInfo['filename']
                ]);
                
                return redirect()->route('packages.show', $package->bundle_id)
                    ->with('success', "Release {$release->version} created successfully with artifact. Download URL: <a href=\"{$downloadUrl}\" target=\"_blank\">{$downloadUrl}</a>")
                    ->with('release_info', [
                        'version' => $release->version,
                        'file_path' => $finalFullPath,
                        'file_size' => $fileSizeFormatted,
                        'processing_time' => $processingDuration,
                        'download_url' => $downloadUrl,
                    ]);
            }
                
        } catch (\Exception $e) {
            Log::error('Release upload failed', [
                'package_id' => $package->id,
                'bundle_id' => $package->bundle_id,
                'version' => $request->version,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String(),
            ]);
            
            return back()
                ->withInput()
                ->withErrors(['artifact' => 'Failed to upload release: ' . $e->getMessage()]);
        }
    }

    /**
     * Format release for display
     */
    protected function formatReleaseForDisplay($release): array
    {
        $statusLabel = $this->getStatusLabel($release->release_status);
        $statusBadge = $this->getStatusBadge($release->release_status);
        
        // Get download count for this release
        $downloadCount = \App\Models\DownloadHistory::where('release_id', $release->id)->count();
        
        return [
            'id' => $release->id,
            'version' => $release->version ?? 'N/A',
            'channel' => $release->channel ?? 'N/A',
            'status' => $statusLabel,
            'status_badge' => $statusBadge,
            'created_at' => $release->create_time?->format('Y-m-d H:i:s') ?? 'N/A',
            'download_count' => $downloadCount,
        ];
    }

    /**
     * Get human-readable label for release status
     */
    protected function getStatusLabel(?int $status): string
    {
        if ($status === null) {
            return 'Unknown';
        }
        
        return "Status {$status}";
    }

    /**
     * Get Bootstrap badge class for release status
     */
    protected function getStatusBadge(?int $status): string
    {
        if ($status === null) {
            return 'bg-secondary';
        }
        
        return 'bg-info';
    }

    /**
     * Format bytes to human-readable size
     * 
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Process and sanitize changelog text
     * 
     * @param string|null $changelog Raw changelog text
     * @return string|null Processed changelog
     */
    protected function processChangelog(?string $changelog): ?string
    {
        if (empty($changelog)) {
            return null;
        }

        $lines = explode("\n", $changelog);
        $processedLines = [];

        foreach ($lines as $line) {
            // Trim whitespaces
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                $processedLines[] = '';
                continue;
            }
            
            // Trim leading "-"
            $line = ltrim($line, '-');
            
            // Trim whitespaces again
            $line = trim($line);
            
            // Skip if line is now empty after trimming
            if (empty($line)) {
                $processedLines[] = '';
                continue;
            }
            
            // Make sure starts with uppercase
            $line = mb_strtoupper(mb_substr($line, 0, 1)) . mb_substr($line, 1);
            
            // Make sure ends with a dot (if it doesn't already)
            if (!preg_match('/[.!?]$/', $line)) {
                $line .= '.';
            }
            
            // Add " - " at the beginning
            $line = ' - ' . $line;
            
            $processedLines[] = $line;
        }

        return implode("\n", $processedLines);
    }

    /**
     * Show the form for editing a release
     */
    public function editRelease(Package $package, Release $release)
    {
        // Ensure the release belongs to the package
        if ($release->package_id !== $package->id) {
            abort(404);
        }
        
        $release->load(['artifacts', 'dependencies']);
        $storageService = new ReleaseStorageService();
        
        // Check if this is the first release (no other releases exist)
        $isFirstRelease = $package->releases()->where('id', '!=', $release->id)->count() === 0;
        
        // Check if this is the oldest release (earliest create_time)
        $isOldestRelease = false;
        if (!$isFirstRelease) {
            $oldestRelease = $package->releases()->orderBy('create_time', 'asc')->first();
            $isOldestRelease = $oldestRelease && $oldestRelease->id === $release->id;
        }
        
        // Find ancestor release with references
        $ancestorRelease = $package->findAncestorReleaseWithReferences($release);
        $hasAncestorReferences = $ancestorRelease !== null;
        
        // Prepare artifact information for the view
        $artifactInfo = null;
        if ($release->artifacts->isNotEmpty()) {
            $artifact = $release->artifacts->first();
            $processedPath = $artifact->url;
            $processedFullPath = $processedPath ? $storageService->getFullPath($processedPath) : null;
            $processedSize = $processedFullPath && file_exists($processedFullPath) ? filesize($processedFullPath) : 0;
            $processedSizeKB = round($processedSize / 1024, 2);
            
            // Try to find the original uploaded file path
            $uploadedPath = null;
            $uploadedFullPath = null;
            $uploadedSize = 0;
            $uploadedSizeKB = 0;
            $uploadedExists = false;
            
            // Check if url_meta contains the original path
            if ($artifact->url_meta) {
                $uploadedPath = $artifact->url_meta;
                $uploadedFullPath = $storageService->getFullPath($uploadedPath);
                $uploadedExists = file_exists($uploadedFullPath);
                if ($uploadedExists) {
                    $uploadedSize = filesize($uploadedFullPath);
                    $uploadedSizeKB = round($uploadedSize / 1024, 2);
                }
            } else {
                // Try to reconstruct the path from upload_date
                // Original files are stored in: incoming/{bundle_id}/{date}/{filename}
                if ($artifact->upload_date) {
                    $date = $artifact->upload_date->format('Y-m-d');
                    $incomingDir = "incoming/{$package->bundle_id}/{$date}";
                    $incomingFullPath = $storageService->getFullPath($incomingDir);
                    
                    // Check if directory exists and find files
                    if (is_dir($incomingFullPath)) {
                        $files = glob($incomingFullPath . '/*');
                        if (!empty($files)) {
                            // Use the first file found (there should typically be only one)
                            $uploadedFullPath = $files[0];
                            // Get the relative path by removing the storage/app base path
                            $storageBasePath = Storage::disk('local')->path('');
                            $uploadedPath = str_replace($storageBasePath, '', $uploadedFullPath);
                            $uploadedPath = str_replace('\\', '/', $uploadedPath);
                            $uploadedPath = ltrim($uploadedPath, '/');
                            $uploadedExists = true;
                            $uploadedSize = filesize($uploadedFullPath);
                            $uploadedSizeKB = round($uploadedSize / 1024, 2);
                        }
                    }
                }
            }
            
            $artifactInfo = [
                'artifact' => $artifact,
                'processed_path' => $processedPath,
                'processed_full_path' => $processedFullPath,
                'processed_filename' => $processedPath ? basename($processedPath) : null,
                'processed_size_bytes' => $processedSize,
                'processed_size_kb' => $processedSizeKB,
                'processed_exists' => $processedFullPath && file_exists($processedFullPath),
                'uploaded_path' => $uploadedPath,
                'uploaded_full_path' => $uploadedFullPath,
                'uploaded_size_bytes' => $uploadedSize,
                'uploaded_size_kb' => $uploadedSizeKB,
                'uploaded_exists' => $uploadedExists,
            ];
        }
        
        return view('releases.edit', compact('package', 'release', 'artifactInfo', 'storageService', 'isFirstRelease', 'isOldestRelease', 'ancestorRelease', 'hasAncestorReferences'));
    }

    /**
     * Update the specified release
     */
    public function updateRelease(Request $request, Package $package, Release $release)
    {
        // Ensure the release belongs to the package
        if ($release->package_id !== $package->id) {
            abort(404);
        }

        // Check if this is the first release (no other releases exist)
        $isFirstRelease = $package->releases()->where('id', '!=', $release->id)->count() === 0;

        // Filter out empty reference rows (rows without bundle_id) before validation
        // For first release, we ignore entries without bundle_id instead of validating
        if ($request->has('references') && is_array($request->references)) {
            $request->merge([
                'references' => array_values(array_filter($request->references, function($ref) {
                    return !empty($ref['bundle_id']) && trim($ref['bundle_id']) !== '';
                }))
            ]);
        }

        $validationRules = [
            'version' => 'required|string|max:45',
            'changelog' => 'nullable|string',
            'upload_name' => 'nullable|string|max:255',
            'processed_filename' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9._-]+\.(tgz|tar\\.gz)$/i'],
            'references' => 'nullable|array',
            'references.*.version' => 'nullable|string|max:45',
        ];

        // Only require bundle_id if it's not the first release
        // For first release, we just ignore entries without bundle_id (already filtered above)
        if (!$isFirstRelease) {
            $validationRules['references.*.bundle_id'] = 'required_with:references|string|max:255';
        } else {
            // For first release, bundle_id is optional (we'll just ignore empty ones)
            $validationRules['references.*.bundle_id'] = 'nullable|string|max:255';
        }

        $validationMessages = [
            'version.required' => 'Version is required.',
            'processed_filename.regex' => 'Processed filename must be a valid .tgz or .tar.gz filename (alphanumeric, dots, dashes, underscores only).',
        ];

        // Only add bundle_id validation message if it's not the first release
        if (!$isFirstRelease) {
            $validationMessages['references.*.bundle_id.required_with'] = 'Bundle ID is required for each reference.';
        }

        // Only validate channel if feature is enabled
        if (config('app.use_feature_channels')) {
            $validationRules['channel'] = 'nullable|string|in:' . implode(',', Channel::all());
            $validationMessages['channel.in'] = 'Invalid channel value.';
        }

        // Only validate release_status if feature is enabled
        if (config('app.use_feature_publish_status')) {
            $validationRules['release_status'] = 'required|integer|in:' . implode(',', array_keys(ReleaseStatus::all()));
            $validationMessages['release_status.required'] = 'Release status is required.';
            $validationMessages['release_status.in'] = 'Invalid release status value.';
        }

        $request->validate($validationRules, $validationMessages);

        $release->version = trim($request->version);
        
        // Only update channel if feature is enabled
        if (config('app.use_feature_channels')) {
            $release->channel = $request->channel ? trim($request->channel) : null;
        }
        
        // Only update release_status if feature is enabled
        if (config('app.use_feature_publish_status')) {
            $release->release_status = $request->release_status;
        }
        
        $release->changelog = $this->processChangelog($request->changelog);
        $release->save();

        // Update artifact information
        $release->load('artifacts');
        if ($release->artifacts->isNotEmpty()) {
            $artifact = $release->artifacts->first();
            $storageService = new ReleaseStorageService();
            
            // Update upload name if provided
            if ($request->has('upload_name')) {
                $artifact->upload_name = trim($request->upload_name);
            }
            
            // Update processed filename if provided
            if ($request->has('processed_filename') && !empty(trim($request->processed_filename))) {
                $newFilename = trim($request->processed_filename);
                $newPath = "incoming_processed/{$newFilename}";
                
                // If artifact already has a URL, try to rename the file
                if ($artifact->url) {
                    $oldPath = $artifact->url;
                    $oldFilename = basename($oldPath);
                    
                    // Only rename file if filename actually changed
                    if ($oldFilename !== $newFilename) {
                        $oldFullPath = $storageService->getFullPath($oldPath);
                        $newFullPath = $storageService->getFullPath($newPath);
                        
                        if (file_exists($oldFullPath)) {
                            // Create directory if needed (should already exist, but just in case)
                            $newDir = dirname($newFullPath);
                            if (!is_dir($newDir)) {
                                mkdir($newDir, 0755, true);
                            }
                            
                            // Attempt to rename the file
                            if (rename($oldFullPath, $newFullPath)) {
                                Log::info('Renamed processed artifact file', [
                                    'artifact_id' => $artifact->id,
                                    'old_path' => $oldPath,
                                    'old_filename' => $oldFilename,
                                    'new_path' => $newPath,
                                    'new_filename' => $newFilename,
                                ]);
                            } else {
                                Log::warning('Failed to rename processed artifact file, but updating URL anyway', [
                                    'artifact_id' => $artifact->id,
                                    'old_path' => $oldFullPath,
                                    'new_path' => $newFullPath,
                                ]);
                            }
                        } else {
                            Log::warning('Old processed artifact file does not exist for rename, but updating URL anyway', [
                                'artifact_id' => $artifact->id,
                                'old_path' => $oldFullPath,
                            ]);
                        }
                    }
                }
                
                // Always update the URL in the database with the new filename
                $artifact->url = $newPath;
                Log::info('Updated processed artifact URL', [
                    'artifact_id' => $artifact->id,
                    'new_path' => $newPath,
                    'new_filename' => $newFilename,
                ]);
            }
            
            $artifact->save();
        }

        // Update references (dependencies)
        // Check if we should inherit (toggle is on) - if a hidden field indicates inheritance
        $shouldInherit = $request->has('inherit_references') && $request->inherit_references === '1';
        
        if (!$shouldInherit) {
            // User has toggled off inheritance, so update dependencies
            // Delete existing dependencies
            $release->dependencies()->delete();
            
            // Add new dependencies if provided
            if ($request->has('references') && is_array($request->references)) {
                foreach ($request->references as $ref) {
                    if (!empty($ref['bundle_id'])) {
                        PackageDependency::create([
                            'release_id' => $release->id,
                            'bundle_id' => trim($ref['bundle_id']),
                            'version' => !empty($ref['version']) ? trim($ref['version']) : null,
                        ]);
                    }
                }
            }
            // If no references provided and toggle is off, dependencies are cleared (handled by delete above)
        }
        // If shouldInherit is true, we don't update dependencies (keep existing or inherit from ancestor)

        return redirect()->route('packages.show', $package->bundle_id)
            ->with('success', 'Release updated successfully.');
    }

    /**
     * Delete the specified release
     */
    public function destroyRelease(Request $request, Package $package, Release $release)
    {
        // Ensure the release belongs to the package
        if ($release->package_id !== $package->id) {
            abort(404);
        }

        $storageService = new ReleaseStorageService();
        $version = $release->version;
        $releaseId = $release->id;
        
        // Load relationships
        $release->load('artifacts', 'dependencies', 'dependentReleases', 'downloadHistory');
        
        // Delete artifact files
        foreach ($release->artifacts as $artifact) {
            // Delete processed file if it exists
            if ($artifact->url) {
                try {
                    $storageService->deleteFile($artifact->url);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete processed artifact file', [
                        'artifact_id' => $artifact->id,
                        'path' => $artifact->url,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Delete uploaded file if url_meta exists
            if ($artifact->url_meta) {
                try {
                    $storageService->deleteFile($artifact->url_meta);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete uploaded artifact file', [
                        'artifact_id' => $artifact->id,
                        'path' => $artifact->url_meta,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Try to delete from incoming directory if upload_date exists
            if ($artifact->upload_date) {
                try {
                    $date = $artifact->upload_date->format('Y-m-d');
                    $incomingDir = "incoming/{$package->bundle_id}/{$date}";
                    $incomingFullPath = $storageService->getFullPath($incomingDir);
                    
                    if (is_dir($incomingFullPath)) {
                        $files = glob($incomingFullPath . '/*');
                        foreach ($files as $file) {
                            if (is_file($file)) {
                                unlink($file);
                            }
                        }
                        // Remove directory if empty
                        if (count(glob($incomingFullPath . '/*')) === 0) {
                            rmdir($incomingFullPath);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to delete uploaded file from incoming directory', [
                        'artifact_id' => $artifact->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        // Delete dependencies where this release is the dependent
        $release->dependencies()->delete();
        
        // Delete dependencies where this release is the dependency
        $release->dependentReleases()->delete();
        
        // Delete download history
        $release->downloadHistory()->delete();
        
        // Delete artifacts
        $release->artifacts()->delete();
        
        // Delete the release
        $release->delete();
        
        Log::info('Release deleted', [
            'package_id' => $package->id,
            'bundle_id' => $package->bundle_id,
            'release_id' => $releaseId,
            'version' => $version,
            'user_id' => Auth::id(),
        ]);
        
        return redirect()->route('packages.show', $package->bundle_id)
            ->with('success', "Release {$version} deleted successfully.");
    }

    /**
     * Download the artifact for a release
     */
    public function downloadArtifact(Request $request, Package $package, Release $release)
    {
        // Ensure the release belongs to the package
        if ($release->package_id !== $package->id) {
            abort(404);
        }

        $release->load('artifacts');
        $artifact = $release->artifacts->first();
        
        if (!$artifact) {
            abort(404, 'No artifact found for this release.');
        }

        $storageService = new ReleaseStorageService();
        
        if (!$storageService->fileExists($artifact->url)) {
            abort(404, 'Artifact file not found.');
        }

        // Log the download
        DownloadHistory::create([
            'release_id' => $release->id,
            'artifact_id' => $artifact->id,
            'additional_data' => [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'download_type' => 'processed_archive',
            ],
            'timestamp' => now(),
        ]);

        $fullPath = $storageService->getFullPath($artifact->url);
        $downloadName = $artifact->upload_name ?: basename($artifact->url);

        return response()->download($fullPath, $downloadName);
    }

    /**
     * Download the original uploaded file for a release
     */
    public function downloadUploadedFile(Request $request, Package $package, Release $release)
    {
        // Ensure the release belongs to the package
        if ($release->package_id !== $package->id) {
            abort(404);
        }

        $release->load('artifacts');
        $artifact = $release->artifacts->first();
        
        if (!$artifact) {
            abort(404, 'No artifact found for this release.');
        }

        $storageService = new ReleaseStorageService();
        
        // Try to find the original uploaded file path (same logic as editRelease)
        $uploadedPath = null;
        $uploadedFullPath = null;
        
        // Check if url_meta contains the original path
        if ($artifact->url_meta) {
            $uploadedPath = $artifact->url_meta;
            $uploadedFullPath = $storageService->getFullPath($uploadedPath);
        } else {
            // Try to reconstruct the path from upload_date
            // Original files are stored in: incoming/{bundle_id}/{date}/{filename}
            if ($artifact->upload_date) {
                $date = $artifact->upload_date->format('Y-m-d');
                $incomingDir = "incoming/{$package->bundle_id}/{$date}";
                $incomingFullPath = $storageService->getFullPath($incomingDir);
                
                // Check if directory exists and find files
                if (is_dir($incomingFullPath)) {
                    $files = glob($incomingFullPath . '/*');
                    if (!empty($files)) {
                        // Use the first file found (there should typically be only one)
                        $uploadedFullPath = $files[0];
                        // Get the relative path by removing the storage/app base path
                        $storageBasePath = Storage::disk('local')->path('');
                        $uploadedPath = str_replace($storageBasePath, '', $uploadedFullPath);
                        $uploadedPath = str_replace('\\', '/', $uploadedPath);
                        $uploadedPath = ltrim($uploadedPath, '/');
                    }
                }
            }
        }
        
        // Check if file exists
        if (!$uploadedPath) {
            abort(404, 'Uploaded file path not found. The file may have been deleted after processing.');
        }
        
        // Use the full path if we have it, otherwise construct it
        if (!$uploadedFullPath) {
            $uploadedFullPath = $storageService->getFullPath($uploadedPath);
        }
        
        if (!file_exists($uploadedFullPath)) {
            abort(404, 'Uploaded file not found at: ' . $uploadedPath);
        }

        // Log the download
        DownloadHistory::create([
            'release_id' => $release->id,
            'artifact_id' => $artifact->id,
            'additional_data' => [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'download_type' => 'uploaded_file',
            ],
            'timestamp' => now(),
        ]);

        $downloadName = $artifact->upload_name ?: basename($uploadedPath);

        return response()->download($uploadedFullPath, $downloadName);
    }

    /**
     * Check if a processed filename exists
     */
    public function checkFilename(Request $request, Package $package, Release $release)
    {
        // Ensure the release belongs to the package
        if ($release->package_id !== $package->id) {
            return response()->json(['exists' => false], 404);
        }

        $filename = $request->query('filename');
        if (!$filename) {
            return response()->json(['exists' => false], 400);
        }

        $storageService = new ReleaseStorageService();
        $path = "incoming_processed/{$filename}";
        $exists = $storageService->fileExists($path);

        return response()->json(['exists' => $exists]);
    }

    /**
     * Get references from ancestor release
     */
    public function getAncestorReferences(Package $package, Release $release)
    {
        // Ensure the release belongs to the package
        if ($release->package_id !== $package->id) {
            return response()->json(['error' => 'Release not found'], 404);
        }

        $ancestorRelease = $package->findAncestorReleaseWithReferences($release);
        
        if (!$ancestorRelease) {
            return response()->json(['references' => []]);
        }

        $ancestorRelease->load('dependencies');
        $references = $ancestorRelease->dependencies->map(function ($dep) {
            return [
                'bundle_id' => $dep->bundle_id,
                'version' => $dep->version,
            ];
        })->filter(function ($ref) {
            return !empty($ref['bundle_id']);
        })->values();

        return response()->json([
            'references' => $references,
            'ancestor_version' => $ancestorRelease->version,
        ]);
    }

    /**
     * Bump patch version (e.g., 1.2.3 -> 1.2.4)
     */
    private function bumpPatchVersion(string $version): string
    {
        $parts = explode('.', $version);
        $major = (int)($parts[0] ?? 0);
        $minor = (int)($parts[1] ?? 0);
        $patch = (int)($parts[2] ?? 0);
        
        $patch++;
        
        return "{$major}.{$minor}.{$patch}";
    }

    /**
     * Reprocess the original uploaded file in place
     */
    public function reprocessRelease(Request $request, Package $package, Release $release)
    {
        // Ensure the release belongs to the package
        if ($release->package_id !== $package->id) {
            abort(404);
        }

        $release->load('artifacts');
        if ($release->artifacts->isEmpty()) {
            return redirect()->route('packages.releases.edit', ['package' => $package->bundle_id, 'release' => $release->id])
                ->with('error', 'No artifact found for this release.');
        }

        $artifact = $release->artifacts->first();
        $storageService = new ReleaseStorageService();

        // Find the original uploaded file
        $uploadedPath = null;
        $uploadedFullPath = null;

        // First, try to get from url_meta (stored original path)
        if ($artifact->url_meta && strpos($artifact->url_meta, 'incoming/') === 0) {
            $uploadedPath = $artifact->url_meta;
            $uploadedFullPath = $storageService->getFullPath($uploadedPath);
            if (!file_exists($uploadedFullPath)) {
                $uploadedPath = null;
            }
        }

        // If not found, try to get from artifact URL if it's still in incoming
        if (!$uploadedPath && $artifact->url && strpos($artifact->url, 'incoming/') === 0) {
            $uploadedPath = $artifact->url;
            $uploadedFullPath = $storageService->getFullPath($uploadedPath);
            if (!file_exists($uploadedFullPath)) {
                $uploadedPath = null;
            }
        }

        // If not found, try to reconstruct from upload_date
        if (!$uploadedPath && $artifact->upload_date) {
            $date = $artifact->upload_date->format('Y-m-d');
            $incomingDir = "incoming/{$package->bundle_id}/{$date}";
            $incomingFullPath = $storageService->getFullPath($incomingDir);
            
            if (is_dir($incomingFullPath)) {
                $files = glob($incomingFullPath . '/*');
                if (!empty($files)) {
                    $uploadedFullPath = $files[0];
                    $storageBasePath = Storage::disk('local')->path('');
                    $uploadedPath = str_replace($storageBasePath, '', $uploadedFullPath);
                    $uploadedPath = str_replace('\\', '/', $uploadedPath);
                    $uploadedPath = ltrim($uploadedPath, '/');
                }
            }
        }

        if (!$uploadedPath || !file_exists($storageService->getFullPath($uploadedPath))) {
            return redirect()->route('packages.releases.edit', ['package' => $package->bundle_id, 'release' => $release->id])
                ->with('error', 'Original uploaded file not found. Cannot reprocess.');
        }

        try {
            // Delete the old processed file if it exists
            if ($artifact->url && strpos($artifact->url, 'incoming_processed/') === 0) {
                $oldProcessedPath = $storageService->getFullPath($artifact->url);
                if (file_exists($oldProcessedPath)) {
                    unlink($oldProcessedPath);
                }
            }

            // Reset artifact status
            $artifact->status = ReleaseProcessingService::STATUS_UPLOADED;
            $artifact->save();

            // Process the release
            $processingService = new ReleaseProcessingService();
            $processedInfo = $processingService->processRelease(
                $uploadedPath,
                $package,
                $release,
                $artifact,
                $release->version
            );

            Log::info('Release reprocessed in place', [
                'package_id' => $package->id,
                'release_id' => $release->id,
                'artifact_id' => $artifact->id,
                'version' => $release->version,
            ]);

            return redirect()->route('packages.releases.edit', ['package' => $package->bundle_id, 'release' => $release->id])
                ->with('success', "Release {$release->version} reprocessed successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to reprocess release', [
                'package_id' => $package->id,
                'release_id' => $release->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('packages.releases.edit', ['package' => $package->bundle_id, 'release' => $release->id])
                ->with('error', 'Failed to reprocess release: ' . $e->getMessage());
        }
    }

    /**
     * Reprocess and create a new release with bumped patch version
     */
    public function reprocessReleaseWithNewVersion(Request $request, Package $package, Release $release)
    {
        // Ensure the release belongs to the package
        if ($release->package_id !== $package->id) {
            abort(404);
        }

        $release->load('artifacts');
        if ($release->artifacts->isEmpty()) {
            return redirect()->route('packages.releases.edit', ['package' => $package->bundle_id, 'release' => $release->id])
                ->with('error', 'No artifact found for this release.');
        }

        $artifact = $release->artifacts->first();
        $storageService = new ReleaseStorageService();

        // Find the original uploaded file
        $uploadedPath = null;
        $uploadedFullPath = null;

        // First, try to get from url_meta (stored original path)
        if ($artifact->url_meta && strpos($artifact->url_meta, 'incoming/') === 0) {
            $uploadedPath = $artifact->url_meta;
            $uploadedFullPath = $storageService->getFullPath($uploadedPath);
            if (!file_exists($uploadedFullPath)) {
                $uploadedPath = null;
            }
        }

        // If not found, try to get from artifact URL if it's still in incoming
        if (!$uploadedPath && $artifact->url && strpos($artifact->url, 'incoming/') === 0) {
            $uploadedPath = $artifact->url;
            $uploadedFullPath = $storageService->getFullPath($uploadedPath);
            if (!file_exists($uploadedFullPath)) {
                $uploadedPath = null;
            }
        }

        // If not found, try to reconstruct from upload_date
        if (!$uploadedPath && $artifact->upload_date) {
            $date = $artifact->upload_date->format('Y-m-d');
            $incomingDir = "incoming/{$package->bundle_id}/{$date}";
            $incomingFullPath = $storageService->getFullPath($incomingDir);
            
            if (is_dir($incomingFullPath)) {
                $files = glob($incomingFullPath . '/*');
                if (!empty($files)) {
                    $uploadedFullPath = $files[0];
                    $storageBasePath = Storage::disk('local')->path('');
                    $uploadedPath = str_replace($storageBasePath, '', $uploadedFullPath);
                    $uploadedPath = str_replace('\\', '/', $uploadedPath);
                    $uploadedPath = ltrim($uploadedPath, '/');
                }
            }
        }

        if (!$uploadedPath || !file_exists($storageService->getFullPath($uploadedPath))) {
            return redirect()->route('packages.releases.edit', ['package' => $package->bundle_id, 'release' => $release->id])
                ->with('error', 'Original uploaded file not found. Cannot reprocess.');
        }

        try {
            // Bump patch version
            $newVersion = $this->bumpPatchVersion($release->version);

            // Create new release
            $newRelease = new Release();
            $newRelease->package_id = $package->id;
            $newRelease->version = $newVersion;
            $newRelease->channel = $release->channel;
            $newRelease->release_status = $release->release_status ?? \App\Enums\ReleaseStatus::default();
            $newRelease->changelog = "Reprocessed from version {$release->version}";
            $newRelease->user_id = Auth::id();
            $newRelease->save();

            // Create new artifact
            $newArtifact = new ReleaseArtifact();
            $newArtifact->release_id = $newRelease->id;
            $newArtifact->upload_name = $artifact->upload_name;
            $newArtifact->upload_date = now();
            $newArtifact->status = ReleaseProcessingService::STATUS_UPLOADED;
            $newArtifact->save();

            // Process the release
            $processingService = new ReleaseProcessingService();
            $processedInfo = $processingService->processRelease(
                $uploadedPath,
                $package,
                $newRelease,
                $newArtifact,
                $newVersion
            );

            Log::info('Release reprocessed with new version', [
                'package_id' => $package->id,
                'original_release_id' => $release->id,
                'new_release_id' => $newRelease->id,
                'original_version' => $release->version,
                'new_version' => $newVersion,
            ]);

            return redirect()->route('packages.releases.edit', ['package' => $package->bundle_id, 'release' => $newRelease->id])
                ->with('success', "New release {$newVersion} created and processed successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to reprocess release with new version', [
                'package_id' => $package->id,
                'release_id' => $release->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('packages.releases.edit', ['package' => $package->bundle_id, 'release' => $release->id])
                ->with('error', 'Failed to reprocess release: ' . $e->getMessage());
        }
    }

    /**
     * Inspect tarball structure and return JSON with directory tree and package.json
     */
    public function inspectTarball(Package $package, Release $release)
    {
        // Ensure the release belongs to the package
        if ($release->package_id !== $package->id) {
            return response()->json(['error' => 'Release not found'], 404);
        }

        $release->load('artifacts');
        if ($release->artifacts->isEmpty()) {
            return response()->json(['error' => 'No artifact found'], 404);
        }

        $artifact = $release->artifacts->first();
        $storageService = new ReleaseStorageService();
        
        if (!$artifact->url) {
            return response()->json(['error' => 'Artifact URL not found'], 404);
        }

        $tarballPath = $storageService->getFullPath($artifact->url);
        if (!file_exists($tarballPath)) {
            return response()->json(['error' => 'Tarball file not found'], 404);
        }

        $tempDir = null;
        try {
            // Extract tarball to temp directory
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tarball-inspect-' . uniqid();
            if (!mkdir($tempDir, 0755, true)) {
                throw new \RuntimeException("Failed to create temp directory");
            }

            $phar = new \PharData($tarballPath);
            $phar->extractTo($tempDir);

            // Build directory tree structure
            $tree = $this->buildDirectoryTree($tempDir, $tempDir, '', true);
            
            // Get package.json content
            $packageJsonPath = $tempDir . DIRECTORY_SEPARATOR . 'package.json';
            $packageJson = null;
            $packageJsonContent = null;
            if (file_exists($packageJsonPath)) {
                $packageJsonContent = file_get_contents($packageJsonPath);
                $packageJson = json_decode($packageJsonContent, true);
            }

            return response()->json([
                'tree' => $tree,
                'package_json' => $packageJson,
                'package_json_raw' => $packageJsonContent,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to inspect tarball', [
                'release_id' => $release->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to inspect tarball: ' . $e->getMessage()], 500);
        } finally {
            // Clean up temp directory
            if ($tempDir && is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
        }
    }

    /**
     * Build directory tree structure with validation
     */
    private function buildDirectoryTree(string $basePath, string $currentPath, string $prefix = '', bool $isRoot = false, int $maxItems = 200): array
    {
        $items = [];
        $entries = array_filter(scandir($currentPath), function($item) {
            return $item !== '.' && $item !== '..';
        });
        
        // Limit total items to prevent excessive output
        $totalEntries = count($entries);
        $entries = array_slice($entries, 0, $maxItems);
        $hasMore = $totalEntries > $maxItems;
        
        // Sort: directories first, then files
        usort($entries, function($a, $b) use ($currentPath) {
            $aPath = $currentPath . DIRECTORY_SEPARATOR . $a;
            $bPath = $currentPath . DIRECTORY_SEPARATOR . $b;
            $aIsDir = is_dir($aPath);
            $bIsDir = is_dir($bPath);
            
            if ($aIsDir && !$bIsDir) return -1;
            if (!$aIsDir && $bIsDir) return 1;
            return strcmp($a, $b);
        });
        
        $standardDirs = ['Editor', 'Runtime', 'Tests', 'Documentation'];
        $standardFiles = ['package.json', 'README.md', 'CHANGELOG.md', 'LICENSE.md'];
        
        foreach ($entries as $index => $entry) {
            $entryPath = $currentPath . DIRECTORY_SEPARATOR . $entry;
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $entryPath);
            $relativePath = str_replace('\\', '/', $relativePath);
            if ($isRoot) {
                $relativePath = $entry;
            }
            
            $isLast = $index === count($entries) - 1;
            $currentPrefix = $prefix . ($isLast ? '└── ' : '├── ');
            $nextPrefix = $prefix . ($isLast ? '    ' : '│   ');
            
            if (is_dir($entryPath)) {
                $isStandard = in_array($entry, $standardDirs);
                $hasAsmdef = false;
                $asmdefFiles = [];
                
                // Check for .asmdef files in Editor and Runtime
                if (in_array($entry, ['Editor', 'Runtime'])) {
                    $asmdefPattern = $entryPath . DIRECTORY_SEPARATOR . '*.asmdef';
                    $asmdefFiles = glob($asmdefPattern);
                    $hasAsmdef = !empty($asmdefFiles);
                }
                
                $subItems = $this->buildDirectoryTree($basePath, $entryPath, $nextPrefix, false, $maxItems);
                
                $items[] = [
                    'type' => 'directory',
                    'name' => $entry,
                    'path' => $relativePath,
                    'display' => $currentPrefix . $entry . ($isStandard ? ' ✓' : ' ✗'),
                    'is_standard' => $isStandard,
                    'has_asmdef' => $hasAsmdef,
                    'asmdef_files' => array_map('basename', $asmdefFiles),
                    'children' => $subItems,
                ];
            } else {
                $isStandard = in_array($entry, $standardFiles);
                $items[] = [
                    'type' => 'file',
                    'name' => $entry,
                    'path' => $relativePath,
                    'display' => $currentPrefix . $entry . ($isStandard ? ' ✓' : ''),
                    'is_standard' => $isStandard,
                    'size' => filesize($entryPath),
                ];
            }
        }
        
        // Add truncation notice if items were limited
        if ($hasMore) {
            $items[] = [
                'type' => 'truncated',
                'name' => '... (' . ($totalEntries - $maxItems) . ' more items)',
                'display' => $prefix . '└── ... (' . ($totalEntries - $maxItems) . ' more items)',
            ];
        }
        
        return $items;
    }

    /**
     * Recursively remove a directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

