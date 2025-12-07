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

class PackageResponseController extends Controller
{
    /**
     * Generate tarball URL with HTTPS if available (but not on localhost)
     * 
     * @param string $packageName Package bundle ID
     * @param string $filename Tarball filename
     * @return string Full URL to tarball
     */
    private function getTarballUrl(string $packageName, string $filename): string
    {
        $url = route('package.tarball', [
            'packageName' => $packageName,
            'filename' => $filename
        ]);
        
        // Check if we're on localhost
        $host = request()->getHost();
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']) 
            || str_contains($host, 'localhost')
            || str_contains($host, '127.0.0.1');
        
        // Only force HTTPS if not on localhost and HTTPS is available
        if (!$isLocalhost) {
            // Check if request is already secure or behind HTTPS proxy
            $isSecure = request()->secure() 
                || request()->header('X-Forwarded-Proto') === 'https'
                || request()->header('X-Forwarded-Ssl') === 'on'
                || (request()->header('X-Forwarded-Port') && request()->header('X-Forwarded-Port') == '443');
            
            if ($isSecure) {
                // Force HTTPS
                $url = str_replace('http://', 'https://', $url);
            }
        }
        
        return $url;
    }

    public function getPackage(Request $request, $bundle_id)
    {
        // find package by bundle_id
        $package = Package::where('bundle_id', $bundle_id)->first();
        Log::info("getPackagePackage data requested", ['id' => $bundle_id]);


        if (!$package) {
            return response()->json(['error' => 'Package not found'], 404);
        }
        $package_name = $package->product_name;

        // find releases by package (eager load relationships)
        $releases = $package->releases()
            ->with(['artifacts', 'user', 'dependencies.dependencyRelease.package', 'package.creator'])
            ->get();

        // find artifacts by release (flattened collection of all artifacts)
        $artifacts = $releases->flatMap(function ($release) {
            return $release->artifacts;
        });

        // Log decoded values
        // Log::info('getPackage Package data retrieved', [
        //     'package_name' => $package_name,
        //     'package' => $package?->bundle_id,
        //     'release count' => $releases?->count() ,
        // ]);

        // Build JSON response programmatically (similar to StringBuilder in C#)
        $json = [];
        
        // Top-level package identifiers
        $json['_id'] = $package->bundle_id;
        // $json['_rev'] = '3-2ab599b62ea43cd64729052b282360c2'; // Optional: revision tracking
        $json['name'] = $package->bundle_id;
        
        // Determine latest version
        $latestRelease = $releases?->sortByDesc('create_time')->first();
        $latestVersion = $latestRelease ? $latestRelease->version : null;
        
        // dist-tags
        $json['dist-tags'] = [];
        if ($latestVersion) {
            $json['dist-tags']['latest'] = $latestVersion;
        }
        
        // Build versions object by iterating through releases
        $json['versions'] = [];
        $timeData = [];
        $earliestTime = null;
        $latestTime = null;
        
        foreach ($releases as $release) {
            $version = $release->version;
            if (!$version) continue;
            
            // Get release artifacts
            $releaseArtifacts = $release->artifacts;
            $primaryArtifact = $releaseArtifacts->first();
            
            // Build version object
            $versionData = [];
            $scope = $package?->scope?->display_name ?? 'Unknown';
            // Author information (from package creator or release user)
            $author = $release->user ?? $package->creator;
                $versionData['author'] = [
                    'name' =>  $scope
                ];
            
            $versionData['displayName']=$package->product_name ?? $package->bundle_id;
            $versionData['author'] = [
                'name' => $package->scope?->display_name ?? 'Uncategorized'
            ];


            // Dependencies
            $dependencies = [];
            foreach ($release->dependencies as $dep) {
                if ($dep->dependencyRelease) {
                    $depPackage = $dep->dependencyRelease->package;
                    if ($depPackage && $depPackage->bundle_id) {
                        $dependencies[$depPackage->bundle_id] = $dep->dependencyRelease->version;
                    }
                } elseif ($dep->external_dependency) {
                    // Handle external dependencies if stored
                    $dependencies[$dep->external_dependency] = '*';
                }
            }
            $versionData['dependencies'] = $dependencies;
            
            // Package metadata
            $versionData['description'] = $package->description ?? '';
            // $versionData['displayName'] = $package->product_name ?? $package->bundle_id; // Uncomment if needed
            // $versionData['keywords'] = ['unity']; // Uncomment and customize if needed
            // $versionData['license'] = 'Unlicense'; // Uncomment and set from package data if available
            $versionData['name'] = $package->bundle_id;
            
            // Repository (if stored in package or release)
            // $versionData['repository'] = [
            //     'type' => 'git',
            //     'url' => 'git+https://github.com/example/repo.git'
            // ];
            
            // Unity version info (if stored)
            // $versionData['unity'] = '2019.4';
            // $versionData['unityRelease'] = '0f1';
            
            $versionData['version'] = $version;
            
            // Bugs/homepage (if stored)
            // $versionData['bugs'] = ['url' => 'https://github.com/example/repo/issues'];
            // $versionData['homepage'] = 'https://github.com/example/repo#readme';
            
            $versionData['_id'] = $package->bundle_id . '@' . $version;
            // $versionData['_nodeVersion'] = '10.19.0'; // Optional
            // $versionData['_npmVersion'] = '6.14.4'; // Optional
            
            // Distribution/dist information
            if ($primaryArtifact) {
                $versionData['dist'] = [];
                
                // Integrity/shasum (if calculated/stored)
                // $versionData['dist']['integrity'] = 'sha512-...';
                if ($primaryArtifact->shasum) {
                    $versionData['dist']['shasum'] = $primaryArtifact->shasum;
                }
                
                // Tarball URL - construct using bundle_id + version + date format
                if ($primaryArtifact->url) {
                    // Extract filename from artifact URL (simplified path: incoming_processed/filename.tgz)
                    $artifactFilename = basename($primaryArtifact->url);
                    
                    // If filename already matches our format, use it directly
                    // Otherwise, construct it from bundle_id, version, and date
                    if (preg_match('/^[a-zA-Z0-9._-]+-[a-zA-Z0-9._-]+-[0-9]{4}-[0-9]{2}-[0-9]{2}\.tgz$/', $artifactFilename)) {
                        // Filename already in correct format: bundle_id-version-date.tgz
                        $npmFilename = $artifactFilename;
                    } else {
                        // Extract date from filename if possible, otherwise use release creation date
                        $date = $release->create_time ? $release->create_time->format('Y-m-d') : date('Y-m-d');
                        if (preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2})\.tgz$/', $artifactFilename, $matches)) {
                            $date = $matches[1];
                        }
                        $safeDate = preg_replace('/[^a-zA-Z0-9._-]/', '-', $date);
                        $safeBundleId = preg_replace('/[^a-zA-Z0-9._-]/', '_', $package->bundle_id);
                        $safeVersion = preg_replace('/[^a-zA-Z0-9._-]/', '_', $version);
                        
                        // Construct filename: bundle_id-version-date.tgz
                        $npmFilename = "{$safeBundleId}-{$safeVersion}-{$safeDate}.tgz";
                    }
                    
                    // Build npm-style tarball URL using route helper to ensure correct base path
                    $versionData['dist']['tarball'] = $this->getTarballUrl($package->bundle_id, $npmFilename);
                } else {
                    // Fallback: Build standard npm-style tarball URL using bundle_id + version + date
                    $date = $release->create_time ? $release->create_time->format('Y-m-d') : date('Y-m-d');
                    $safeDate = preg_replace('/[^a-zA-Z0-9._-]/', '-', $date);
                    $safeBundleId = preg_replace('/[^a-zA-Z0-9._-]/', '_', $package->bundle_id);
                    $safeVersion = preg_replace('/[^a-zA-Z0-9._-]/', '_', $version);
                    $filename = "{$safeBundleId}-{$safeVersion}-{$safeDate}.tgz";
                    $versionData['dist']['tarball'] = $this->getTarballUrl($package->bundle_id, $filename);
                }
                
                // File count and size (if stored in url_meta or calculated)
                // $versionData['dist']['fileCount'] = 10;
                // $versionData['dist']['unpackedSize'] = 5984;
                
                // Signatures (if you implement signing)
                // $versionData['dist']['npm-signature'] = '...';
                // $versionData['dist']['signatures'] = [...];
            } else {
                // Even without artifact, provide a dist object (some registries do this)
                // Use bundle_id + version + date format
                $date = $release->create_time ? $release->create_time->format('Y-m-d') : date('Y-m-d');
                $safeDate = preg_replace('/[^a-zA-Z0-9._-]/', '-', $date);
                $safeBundleId = preg_replace('/[^a-zA-Z0-9._-]/', '_', $package->bundle_id);
                $safeVersion = preg_replace('/[^a-zA-Z0-9._-]/', '_', $version);
                $filename = "{$safeBundleId}-{$safeVersion}-{$safeDate}.tgz";
                $versionData['dist'] = [
                    'tarball' => $this->getTarballUrl($package->bundle_id, $filename)
                ];
            }
            
            // // Maintainers
            // if ($author) {
            //     $versionData['maintainers'] = [[
            //         'name' => $author->name ?? 'Unknown',
            //         'email' => $author->email ?? null
            //     ]];
            //     $versionData['_npmUser'] = [
            //         'name' => $author->name ?? 'Unknown',
            //         'email' => $author->email ?? null
            //     ];
            // }
            
            // $versionData['directories'] = [];
            // $versionData['_npmOperationalInternal'] = [...];
            // $versionData['_hasShrinkwrap'] = false;
            
            $json['versions'][$version] = $versionData;
            
            // Build time data
            $releaseTime = $release->create_time;
            if ($releaseTime) {
                // Format: 2020-09-17T05:14:08.335Z (ISO 8601 with milliseconds)
                $timeData[$version] = str_replace('+00:00', 'Z', $releaseTime->toIso8601String());
                
                // Track earliest and latest times
                if (!$earliestTime || $releaseTime < $earliestTime) {
                    $earliestTime = $releaseTime;
                }
                if (!$latestTime || $releaseTime > $latestTime) {
                    $latestTime = $releaseTime;
                }
            }
        }
        
        // Time object
        $json['time'] = [];
        if ($earliestTime) {
            $json['time']['created'] = str_replace('+00:00', 'Z', $earliestTime->toIso8601String());
        }
        foreach ($timeData as $version => $timestamp) {
            $json['time'][$version] = $timestamp;
        }
        if ($latestTime) {
            $json['time']['modified'] = str_replace('+00:00', 'Z', $latestTime->toIso8601String());
        }
        
        // Package-level maintainers
        $maintainer = $package->creator;
        if ($maintainer) {
            $json['maintainers'] = [[
                'name' => $maintainer->name ?? 'Unknown',
                'email' => $maintainer->email ?? null
            ]];
        }
        
        // Package-level metadata
        $json['description'] = $package->description ?? '';
        // $json['homepage'] = 'https://github.com/example/repo#readme';
        // $json['keywords'] = ['unity'];
        // $json['repository'] = [
        //     'type' => 'git',
        //     'url' => 'git+https://github.com/example/repo.git'
        // ];
        
        if ($maintainer) {
            $json['author'] = [
                'name' => $maintainer->name ?? 'Unknown'
            ];
        }
        
   
        
        // Convert to JSON string
        $jsonString = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        // Log::info("getPackagePackage data returning", ['length' => strlen($jsonString)]);
        return response()->make($jsonString, 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            // ->header('Access-Control-Allow-Headers', 'Content-Type')      
            ->header('Content-Type', 'application/json');
    }

    
    public function getPackageWithTrash(Request $request, $trash, $bundle_id)
    {
       

        return $this->getPackage($request, $bundle_id);
    }

    /**
     * Download package tarball (format: /{packageName}/download/{filename})
     */
    public function downloadTarball(Request $request, $bundle_id, $filename)
    {
        Log::info('Tarball download requested', [
            'bundle_id' => $bundle_id,
            'filename' => $filename,
            'request_path' => $request->path(),
        ]);

        // Find package by bundle_id
        $package = Package::where('bundle_id', $bundle_id)->first();
        
        if (!$package) {
            Log::warning('Package not found for tarball download', ['bundle_id' => $bundle_id]);
            return response()->json(['error' => 'Package not found'], 404);
        }

        // Extract version from filename (format: bundle_id-version-date.tgz)
        // Try to match version from filename first
        $version = null;
        // Match format: bundle_id-version-date.tgz
        $safeBundleId = preg_quote(preg_replace('/[^a-zA-Z0-9._-]/', '_', $bundle_id), '/');
        if (preg_match('/^' . $safeBundleId . '-(.+)-[0-9]{4}-[0-9]{2}-[0-9]{2}\.tgz$/', $filename, $matches)) {
            $version = $matches[1];
        } elseif (preg_match('/^' . $safeBundleId . '-(.+)\.tgz$/', $filename, $matches)) {
            // Fallback: bundle_id-version.tgz (old format or custom)
            $version = $matches[1];
        }

        // Find the release - prefer by version if we extracted it, otherwise get latest
        $release = null;
        if ($version) {
            $release = $package->releases()
                ->where('version', $version)
                ->where(function ($query) {
                    $query->where('release_status', ReleaseStatus::PUBLISHED)
                          ->orWhereNull('release_status');
                })
                ->first();
        }

        // If not found by version, try to find by artifact filename
        if (!$release) {
            $releases = $package->releases()
                ->where(function ($query) {
                    $query->where('release_status', ReleaseStatus::PUBLISHED)
                          ->orWhereNull('release_status');
                })
                ->with('artifacts')
                ->get();
            
            foreach ($releases as $rel) {
                foreach ($rel->artifacts as $artifact) {
                    if (!$artifact->url) continue;
                    
                    $artifactFilename = basename($artifact->url);
                    $artifactFilenameTgz = str_replace('.tar.gz', '.tgz', $artifactFilename);
                    
                    // Match by exact filename or without extension
                    if ($artifactFilename === $filename 
                        || $artifactFilenameTgz === $filename
                        || pathinfo($artifactFilename, PATHINFO_FILENAME) === pathinfo($filename, PATHINFO_FILENAME)) {
                        $release = $rel;
                        break 2;
                    }
                }
            }
        }

        // If still not found, get latest published release
        if (!$release) {
            $release = $package->releases()
                ->where(function ($query) {
                    $query->where('release_status', ReleaseStatus::PUBLISHED)
                          ->orWhereNull('release_status');
                })
                ->orderBy('create_time', 'desc')
                ->first();
        }

        if (!$release) {
            Log::warning('Release not found for tarball download', [
                'bundle_id' => $bundle_id,
                'filename' => $filename,
                'version' => $version,
            ]);
            return response()->json(['error' => 'Release not found'], 404);
        }

        // Get the artifact
        $artifact = $release->artifacts->first();
        
        if (!$artifact || !$artifact->url) {
            Log::warning('Artifact not found for tarball download', [
                'bundle_id' => $bundle_id,
                'release_id' => $release->id,
            ]);
            return response()->json(['error' => 'Artifact not found'], 404);
        }

        // Check if file exists
        $storageService = new \App\Services\Storage\ReleaseStorageService();
        if (!$storageService->fileExists($artifact->url)) {
            Log::warning('Artifact file does not exist', [
                'bundle_id' => $bundle_id,
                'artifact_url' => $artifact->url,
                'full_path' => $storageService->getFullPath($artifact->url),
            ]);
            return response()->json(['error' => 'Artifact file not found'], 404);
        }

        // Get full path and serve the file
        $fullPath = $storageService->getFullPath($artifact->url);
        
        // Verify file exists and is readable
        if (!file_exists($fullPath)) {
            Log::error('Tarball file does not exist at full path', [
                'bundle_id' => $bundle_id,
                'full_path' => $fullPath,
                'artifact_url' => $artifact->url,
            ]);
            return response()->json(['error' => 'Tarball file not found'], 404);
        }
        
        if (!is_readable($fullPath)) {
            Log::error('Tarball file is not readable', [
                'bundle_id' => $bundle_id,
                'full_path' => $fullPath,
            ]);
            return response()->json(['error' => 'Tarball file not readable'], 500);
        }
        
        $fileSize = filesize($fullPath);
        if ($fileSize === 0 || $fileSize === false) {
            Log::error('Tarball file is empty', [
                'bundle_id' => $bundle_id,
                'full_path' => $fullPath,
                'file_size' => $fileSize,
            ]);
            return response()->json(['error' => 'Tarball file is empty'], 500);
        }
        
        $downloadName = $artifact->upload_name ?: basename($artifact->url);
        
        // Ensure .tgz extension for download
        if (!preg_match('/\.tgz$/', $downloadName)) {
            $downloadName = str_replace('.tar.gz', '.tgz', $downloadName);
            if (!preg_match('/\.tgz$/', $downloadName)) {
                $downloadName = $bundle_id . '-' . $release->version . '.tgz';
            }
        }

        Log::info('Serving tarball', [
            'bundle_id' => $bundle_id,
            'version' => $release->version,
            'artifact_url' => $artifact->url,
            'full_path' => $fullPath,
            'download_name' => $downloadName,
            'file_size' => $fileSize,
            'file_size_kb' => round($fileSize / 1024, 2),
        ]);

        // Use response()->file() which properly sets Content-Length and avoids chunked encoding
        // Set headers explicitly to ensure they're not overridden
        $response = response()->file($fullPath);
        $response->headers->set('Content-Type', 'application/x-tar');
        $response->headers->set('Content-Length', $fileSize);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $downloadName . '"');
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->headers->set('Cache-Control', 'public, max-age=31536000');
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
        
        return $response;
    }
}
