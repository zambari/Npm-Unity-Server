<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Package;
use App\Models\Release;
use App\Models\ReleaseArtifact;
use App\Models\PackageDependency;
use App\Enums\ReleaseStatus;
use App\Enums\PackageStatus;

class NpmRegistryController extends Controller
{
    /**
     * Get packages from database
     * Only includes published releases (release_status = PUBLISHED or NULL)
     * Only includes published packages (status = PUBLISHED and not disabled)
     */
    private function getPackages()
    {

        // Load packages with their published releases and artifacts
        $packages = Package::with([
            'releases' => function ($query) {
                // Only get published releases
                $query->where(function ($q) {
                    $q->where('release_status', ReleaseStatus::PUBLISHED)
                      ->orWhereNull('release_status'); // NULL is treated as published
                })
                ->orderBy('create_time', 'desc');
            },
            'releases.artifacts',
            'releases.dependencies.dependencyRelease.package',
        ])
        ->where('status', PackageStatus::PUBLISHED)
        ->where('disabled', false)
        ->get();
        
        $result = [];
        
        // Config: generate default version for packages with no versions
        $generateDefaultVersion = config('registry.generate_default_version', true);
        $defaultVersion = config('registry.default_version', '0.0.0');

        foreach ($packages as $package) {
            $versions = [];
            $time = ['created' => $package->created_at->toIso8601String()];

            // Process all published releases
            foreach ($package->releases as $release) {
                // Get the artifact for this release (should have at least one)
                $artifact = $release->artifacts->first();
                
                // Skip releases without artifacts (unless default version)
                if (!$artifact && !$generateDefaultVersion) {
                    continue;
                }

                $versionTime = str_replace('+00:00', 'Z', $release->create_time->toIso8601String());
                
                // Build dependencies array in NPM format: { "bundle_id": "version" }
                $dependencies = $this->formatDependencies($release);
                
                $versionData = [
                    'name' => $package->bundle_id,
                    'version' => $release->version,
                    'dist' => [
                        'tarball' => route('package.tarball', [
                            'packageName' => $package->bundle_id, 
                            'filename' => $package->bundle_id . '-' . $release->version . '.tgz'
                        ]),
                    ],
                    'dependencies' => $dependencies,
                    'time' => $versionTime,
                ];
                
                // Add optional fields if they exist
                // Note: Package model doesn't have author/license fields currently
                // These can be added later if needed
                
                $versions[$release->version] = $versionData;
                $time[$release->version] = $versionTime;
            }

            // Generate default version if package has no versions and feature is enabled
            if (empty($versions) && $generateDefaultVersion) {
                $defaultTime = str_replace('+00:00', 'Z', $package->created_at->toIso8601String());
                $versions[$defaultVersion] = [
                    'name' => $package->bundle_id,
                    'version' => $defaultVersion,
                    'dist' => [
                        'tarball' => route('package.tarball', [
                            'packageName' => $package->bundle_id, 
                            'filename' => $package->bundle_id . '-' . $defaultVersion . '.tgz'
                        ]),
                    ],
                    'dependencies' => [],
                    'time' => $defaultTime,
                ];
                $time[$defaultVersion] = $defaultTime;
            }

            $time['modified'] = $package->updated_at->toIso8601String();

            // Determine latest version
            $latestVersion = null;
            if (!empty($versions)) {
                // Get the highest version number
                uksort($versions, 'version_compare');
                $latestVersion = array_key_last($versions);
            } else {
                $latestVersion = $generateDefaultVersion ? $defaultVersion : null;
            }

            // Skip packages with no versions (unless default version is enabled)
            if (empty($versions) && !$generateDefaultVersion) {
                continue;
            }

            $result[$package->bundle_id] = [
                'name' => $package->bundle_id ,//product_name, // was bundle_id
                'versions' => $versions,
                'dist-tags' => [
                    'latest' => $latestVersion,
                ],
                'time' => $time,
                'description' => $package->description,
                'repository_url' => $package->repository_url,
                'homepage_url' => $package->homepage_url,
            ];
        }

        return $result;
    }

    /**
     * Format dependencies for NPM registry format
     * Returns: { "bundle_id": "version" }
     */
    private function formatDependencies(Release $release): array
    {
        $dependencies = [];
        
        // Load dependencies if not already loaded
        if (!$release->relationLoaded('dependencies')) {
            $release->load('dependencies.dependencyRelease.package');
        }
        
        foreach ($release->dependencies as $dependency) {
            if ($dependency->dependencyRelease && $dependency->dependencyRelease->package) {
                // Internal dependency
                $depPackage = $dependency->dependencyRelease->package;
                $depVersion = $dependency->dependencyRelease->version;
                $dependencies[$depPackage->bundle_id] = $depVersion;
            } elseif ($dependency->external_dependency) {
                // External dependency - format: "package@version" or just "package"
                // For now, we'll try to parse it, but external dependencies might need special handling
                $external = trim($dependency->external_dependency);
                if (strpos($external, '@') !== false) {
                    // Format: "package@version"
                    list($pkg, $ver) = explode('@', $external, 2);
                    $dependencies[trim($pkg)] = trim($ver);
                } else {
                    // Just package name, no version specified
                    $dependencies[$external] = '*';
                }
            }
        }
        
        return $dependencies;
    }

    /**
     * Handle /-/v1/search endpoint
     * This endpoint is used by Unity to search for packages
     */
    public function search(Request $request)
    {
        // Log incoming request details
        Log::info('NPM Search Endpoint Called', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'query_params' => $request->all(),
            'headers' => [
                'user-agent' => $request->header('User-Agent'),
                'accept' => $request->header('Accept'),
                'content-type' => $request->header('Content-Type'),
            ],
            'ip' => $request->ip(),
        ]);

        $query = $request->input('text', '');
        $size = (int) $request->input('size', 20);
        
        $packages = $this->getPackages();
        $results = [];

        foreach ($packages as $bundleId => $packageData) {
            // Skip packages with no versions (unless default version is enabled)
            $generateDefaultVersion = config('registry.generate_default_version', true);
            if (empty($packageData['versions']) && !$generateDefaultVersion) {
                continue;
            }
            
            // Skip if no latest version (shouldn't happen with default version, but safety check)
            if (empty($packageData['dist-tags']['latest'])) {
                continue;
            }

            // If there's a search query, filter by bundle_id
            // Unity searches with scope like "com.zamb" and packages are "com.zamb.*"
            if ($query && stripos($bundleId, $query) === false) {
                continue;
            }

            $latestVersion = $packageData['dist-tags']['latest'];
            $updatedTime = $packageData['time']['modified'] ?? $packageData['time']['created'];
            // Convert to npm's format (Z instead of +00:00)
            $updatedTime = str_replace('+00:00', 'Z', $updatedTime);

            $results[] = [
                'package' => [
                    'name' => $bundleId,
                    'version' => $latestVersion,
                    'date' => $updatedTime,
                    'description' => $packageData['description'] ?? null,
                    'links' => [
                        'homepage' => $packageData['homepage_url'] ?? '',
                        'repository' => $packageData['repository_url'] ?? '',
                    ],
                ],
            ];
        }

        // Limit results
        $results = array_slice($results, 0, $size);

        $response = [
            'objects' => $results,
            'total' => count($results),
            'time' => str_replace('+00:00', 'Z', now()->toIso8601String()),
        ];

        // Log response details
        Log::info('NPM Search Response', [
            'query' => $query,
            'results_count' => count($results),
            'package_names' => array_map(fn($r) => $r['package']['name'], $results),
            'response_total' => $response['total'],
        ]);

        return response()->json($response)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }

    /**
     * Handle /-/all endpoint
     * This endpoint returns all packages in the registry
     */
    public function allPackages(Request $request)
    {
        // Log incoming request details
        Log::info('NPM All Packages Endpoint Called', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'query_params' => $request->all(),
            'headers' => [
                'user-agent' => $request->header('User-Agent'),
                'accept' => $request->header('Accept'),
                'content-type' => $request->header('Content-Type'),
            ],
            'ip' => $request->ip(),
        ]);

        $packages = $this->getPackages();
        
        // Log response details
        Log::info('NPM All Packages Response', [
            'package_count' => count($packages),
            'package_names' => array_keys($packages),
        ]);
        
        return response()->json($packages)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }

    /**
     * Handle individual package requests
     * GET /package-name returns full package metadata
     */
    public function getPackage(Request $request, $bundle_id)
    {
        $packages = $this->getPackages();
        $packageData = $packages[$bundle_id];

        // Log incoming request details
        Log::info('NPM Individual Package Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'package_name' => $bundle_id,// $packageData['name'],
            'query_params' => $request->all(),
            'headers' => [
                'user-agent' => $request->header('User-Agent'),
                'accept' => $request->header('Accept'),
                'content-type' => $request->header('Content-Type'),
            ],
            'ip' => $request->ip(),
        ]);

       
        
        if (!isset($packages[$bundle_id])) {
            Log::warning('Package not found', ['package_name' => $bundle_id]);
            return response()->json(['error' => 'Package not found'], 404);
        }

  
        
        Log::info('NPM Individual Package Response', [
            'package_name' => $packageData['name'],
            'versions' => array_keys($packageData['versions']),
        ]);
        
        return response()->json($packageData)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }

    /**
     * Download package tarball
     */
    public function downloadTarball($bundle_id, $filename)
    {
        $package = Package::where('bundle_id', $bundle_id)
            ->where('status', PackageStatus::PUBLISHED)
            ->where('disabled', false)
            ->first();
        
        if (!$package) {
            return response()->json(['error' => 'Package not found'], 404);
        }

        // Extract version from filename (format: packageName-version.tgz)
        // Remove .tgz extension and package name prefix
        $version = str_replace($bundle_id . '-', '', str_replace('.tgz', '', $filename));
        
        $defaultVersion = config('registry.default_version', '0.0.0');
        
        // Check if this is the default version (no actual file exists)
        if ($version === $defaultVersion) {
            $release = $package->releases()
                ->where('version', $version)
                ->where(function ($query) {
                    $query->where('release_status', ReleaseStatus::PUBLISHED)
                          ->orWhereNull('release_status');
                })
                ->first();
            
            if (!$release) {
                return response()->json([
                    'error' => 'This is an unpublished placeholder version. No tarball available.',
                    'version' => $version,
                    'status' => 'unpublished'
                ], 404);
            }
        }

        // Find the release
        $release = $package->releases()
            ->where('version', $version)
            ->where(function ($query) {
                $query->where('release_status', ReleaseStatus::PUBLISHED)
                      ->orWhereNull('release_status');
            })
            ->first();
        
        if (!$release) {
            return response()->json(['error' => 'Version not found'], 404);
        }

        // Get the artifact for this release
        $artifact = $release->artifacts()->first();
        
        if (!$artifact) {
            return response()->json(['error' => 'Artifact not found for this version'], 404);
        }

        // Check if file exists in storage
        // The artifact->url contains the relative path from storage
        if (!Storage::disk('local')->exists($artifact->url)) {
            Log::error('Tarball file not found in storage', [
                'package' => $bundle_id,
                'version' => $version,
                'artifact_path' => $artifact->url,
            ]);
            return response()->json(['error' => 'Tarball file not found'], 404);
        }

        // Get the full path to the file
        $filePath = Storage::disk('local')->path($artifact->url);
        
        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            $fileSize = 0;
        }
        
        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/x-tar',
            'Content-Length' => $fileSize,
        ]);
    }
}

