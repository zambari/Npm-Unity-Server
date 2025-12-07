<?php

namespace App\Services\Storage;

use App\Models\Package;
use App\Models\Release;
use App\Models\ReleaseArtifact;
use App\Models\MetaFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Carbon\Carbon;

class ReleaseProcessingService
{
    // Status constants for ReleaseArtifact processing
    const STATUS_UPLOADED = 1;
    const STATUS_UNPACKED = 2;
    const STATUS_PROCESSED = 3;
    const STATUS_TARBALL_CREATED = 4;
    const STATUS_COMPLETED = 5;

    /**
     * Standard Unity package structure directories
     */
    private const STANDARD_DIRS = [
        'Editor',
        'Runtime',
        'Tests',
        'Documentation',
    ];

    /**
     * Standard Unity package files
     */
    private const STANDARD_FILES = [
        'package.json',
        'README.md',
        'CHANGELOG.md',
        'LICENSE.md',
    ];

    /**
     * Process an uploaded release file
     * 
     * @param string $uploadedFilePath Path to the uploaded file
     * @param Package $package The package model
     * @param Release $release The release model
     * @param ReleaseArtifact $artifact The artifact model
     * @param string $version The version string
     * @param bool $includeReadme Whether to include README.md from incoming folder
     * @return array Returns ['path' => final tarball path, 'filename' => tarball filename]
     */
    public function processRelease(
        string $uploadedFilePath,
        Package $package,
        Release $release,
        ReleaseArtifact $artifact,
        string $version,
        bool $includeReadme = false
    ): array {
        $storageService = new ReleaseStorageService();
        $fullUploadPath = $storageService->getFullPath($uploadedFilePath);
        $tempDir = null;

        try {
            // Step 1: Unpack the file
            $artifact->status = self::STATUS_UNPACKED;
            $artifact->save();
            Log::info('Unpacking release file', [
                'artifact_id' => $artifact->id,
                'uploaded_file_relative' => $uploadedFilePath,
                'uploaded_file_full_path' => $fullUploadPath,
            ]);

            $tempDir = $this->unpackFile($fullUploadPath);
            
            // Step 1.5: Flatten single root folder if it contains Unity package structure
            $tempDir = $this->flattenRootFolderIfNeeded($tempDir, $package->bundle_id);
            
            // Step 2: Add package.json
            $this->createPackageJson($tempDir, $package, $release, $version);
            
            // Step 3: Check folder structure and log non-standard items
            $this->validateAndLogStructure($tempDir, $package->bundle_id);
            
            // Step 4: Create CHANGELOG.md
            $this->createChangelog($tempDir, $package);
            
            // Step 4.25: Add README.md from incoming folder if requested
            if ($includeReadme) {
                $this->addPackageReadme($tempDir, $package);
            }
            
            // Step 4.5: Generate Unity .meta files
            $this->generateMetaFiles($tempDir, $package);
            
            // Step 5: Update status to processed
            $artifact->status = self::STATUS_PROCESSED;
            $artifact->save();
            Log::info('Package structure processed', [
                'artifact_id' => $artifact->id,
                'temp_dir' => $tempDir,
            ]);
            
            // Step 6: Verify package.json exists before creating tarball
            $packageJsonPath = $tempDir . DIRECTORY_SEPARATOR . 'package.json';
            if (!file_exists($packageJsonPath)) {
                Log::error('package.json not found before tarball creation', [
                    'artifact_id' => $artifact->id,
                    'temp_dir' => $tempDir,
                    'package_json_path' => $packageJsonPath,
                ]);
                throw new \RuntimeException('package.json not found in temp directory before tarball creation');
            }
            
            // Log directory contents for debugging
            $rootItems = array_filter(scandir($tempDir), function($item) {
                return $item !== '.' && $item !== '..';
            });
            Log::info('Directory contents before tarball creation', [
                'artifact_id' => $artifact->id,
                'temp_dir' => $tempDir,
                'root_items' => $rootItems,
                'package_json_exists' => file_exists($packageJsonPath),
            ]);

            // Step 7: Create tarball
            $artifact->status = self::STATUS_TARBALL_CREATED;
            $artifact->save();
            Log::info('Creating tarball', [
                'artifact_id' => $artifact->id,
            ]);

            $tarballInfo = $this->createTarball($tempDir, $package->bundle_id, $version);
            
            // Step 7: Verify tarball structure by extracting it temporarily
            $this->verifyTarballStructure($tarballInfo['path'], $package->bundle_id);
            
            // Step 8: Store tarball in processed location (simplified - no bundle_id/date folders)
            // Filename format: bundle_id-version-date.tgz (already unique)
            $finalPath = "incoming_processed/{$tarballInfo['filename']}";
            
            // Move tarball to storage
            Storage::disk('local')->put($finalPath, file_get_contents($tarballInfo['path']));
            $finalFullPath = $storageService->getFullPath($finalPath);
            
            // Step 8: Clean up temporary files
            $this->cleanup($tempDir, $tarballInfo['path']);
            
            // Step 9: Calculate SHA1 hash of the tarball
            $shasum = sha1_file($finalFullPath);
            
            // Step 10: Update artifact with final path, shasum, and mark as completed
            $artifact->url = $finalPath;
            $artifact->shasum = $shasum;
            $artifact->status = self::STATUS_COMPLETED;
            $artifact->save();
            
            Log::info('Release processing completed', [
                'artifact_id' => $artifact->id,
                'final_path_relative' => $finalPath,
                'final_path_full' => $finalFullPath,
                'tarball_filename' => $tarballInfo['filename'],
            ]);

            return [
                'path' => $finalPath,
                'filename' => $tarballInfo['filename'],
                'full_path' => $finalFullPath,
            ];
            
        } catch (\Exception $e) {
            // Clean up on error
            if ($tempDir && is_dir($tempDir)) {
                $this->removeDirectory($tempDir);
            }
            
            Log::error('Release processing failed', [
                'artifact_id' => $artifact->id,
                'uploaded_file_relative' => $uploadedFilePath,
                'uploaded_file_full_path' => $fullUploadPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Unpack a zip, unitypackage, or tarball file
     * 
     * @param string $filePath Path to the file to unpack
     * @return string Path to the temporary directory
     */
    private function unpackFile(string $filePath): string
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'npm-unity-release-' . uniqid();
        
        if (!mkdir($tempDir, 0755, true)) {
            throw new \RuntimeException("Failed to create temporary directory: {$tempDir}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $filename = basename($filePath);
        
        // Check for tarball files (.tgz, .tar.gz, or .unitypackage)
        // Note: .unitypackage files are actually tar.gz archives, not zip files
        $isTarball = $extension === 'tgz' 
            || $extension === 'gz' 
            || $extension === 'unitypackage'
            || preg_match('/\.tar\.gz$/i', $filename);
        
        if ($extension === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $zip->extractTo($tempDir);
                $zip->close();
                
                Log::info('Extracted zip file', [
                    'file_path' => $filePath,
                    'temp_dir' => $tempDir,
                ]);
            } else {
                throw new \RuntimeException("Failed to open zip file: {$filePath}");
            }
        } elseif ($isTarball) {
            // Extract tarball using PharData (includes .unitypackage files)
            try {
                $phar = new \PharData($filePath);
                $phar->extractTo($tempDir);
                
                Log::info('Extracted tarball file', [
                    'file_path' => $filePath,
                    'file_type' => $extension === 'unitypackage' ? 'unitypackage (tar.gz)' : 'tarball',
                    'temp_dir' => $tempDir,
                ]);
            } catch (\Exception $e) {
                throw new \RuntimeException("Failed to extract tarball file: {$filePath}. Error: " . $e->getMessage());
            }
        } else {
            throw new \RuntimeException("Unsupported file type: {$extension}. Expected zip, unitypackage, tgz, or tar.gz.");
        }

        return $tempDir;
    }

    /**
     * Flatten root folder if it contains Unity package structure
     * If the unpacked zip contains a single folder (not a predefined Unity folder),
     * and that folder contains Unity package structure (Runtime, Editor, etc.),
     * move the contents up one level.
     * 
     * @param string $tempDir Temporary directory after unpacking
     * @param string $bundleId Bundle ID for logging
     * @return string Path to the directory (may be updated if flattened)
     */
    private function flattenRootFolderIfNeeded(string $tempDir, string $bundleId): string
    {
        $items = array_filter(scandir($tempDir), function($item) {
            return $item !== '.' && $item !== '..';
        });
        
        // Check if there's exactly one item and it's a directory
        if (count($items) !== 1) {
            return $tempDir; // Multiple items or no items, don't flatten
        }
        
        $singleItem = reset($items);
        $singleItemPath = $tempDir . DIRECTORY_SEPARATOR . $singleItem;
        
        if (!is_dir($singleItemPath)) {
            return $tempDir; // It's a file, not a folder, don't flatten
        }
        
        // Check if it's a predefined Unity folder - if so, don't flatten
        if (in_array($singleItem, self::STANDARD_DIRS)) {
            return $tempDir; // It's a standard Unity folder, keep structure as is
        }
        
        // Check if this folder contains Unity package structure
        $subItems = array_filter(scandir($singleItemPath), function($item) {
            return $item !== '.' && $item !== '..';
        });
        
        $hasUnityStructure = false;
        foreach ($subItems as $subItem) {
            $subItemPath = $singleItemPath . DIRECTORY_SEPARATOR . $subItem;
            if (is_dir($subItemPath) && in_array($subItem, self::STANDARD_DIRS)) {
                $hasUnityStructure = true;
                break;
            }
        }
        
        if (!$hasUnityStructure) {
            return $tempDir; // Doesn't contain Unity package structure, don't flatten
        }
        
        // Flatten: move all contents from the single folder up one level
        Log::info('Flattening root folder - moving contents up one level', [
            'bundle_id' => $bundleId,
            'root_folder' => $singleItem,
            'temp_dir' => $tempDir,
        ]);
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $singleItemPath,
                \RecursiveDirectoryIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        $singleItemPathNormalized = rtrim(str_replace('\\', '/', $singleItemPath), '/') . '/';
        
        foreach ($iterator as $item) {
            $itemPathNormalized = str_replace('\\', '/', $item->getPathname());
            $relativePath = str_replace($singleItemPathNormalized, '', $itemPathNormalized);
            $targetPath = $tempDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                copy($item->getPathname(), $targetPath);
            }
        }
        
        // Remove the now-empty root folder
        $this->removeDirectory($singleItemPath);
        
        Log::info('Root folder flattened successfully', [
            'bundle_id' => $bundleId,
            'removed_folder' => $singleItem,
        ]);
        
        return $tempDir;
    }

    /**
     * Create package.json file
     * 
     * @param string $dir Directory where package.json should be created
     * @param Package $package The package model
     * @param Release $release The release model
     * @param string $version The version string
     */
    private function createPackageJson(string $dir, Package $package, Release $release, string $version): void
    {
        $packageJsonPath = $dir . DIRECTORY_SEPARATOR . 'package.json';
        
        // Check if package.json already exists
        if (file_exists($packageJsonPath)) {
            $overwritePackageJson = config('app.overrite_package_json', false);
            
            if ($overwritePackageJson) {
                // Delete existing package.json
                unlink($packageJsonPath);
                Log::info('Deleted existing package.json (overrite_package_json is true)', [
                    'bundle_id' => $package->bundle_id,
                    'package_json_path' => $packageJsonPath,
                ]);
            } else {
                // Use existing package.json, don't create a new one
                Log::info('Using already present package.json (overrite_package_json is false)', [
                    'bundle_id' => $package->bundle_id,
                    'package_json_path' => $packageJsonPath,
                ]);
                return;
            }
        }
        
        $package->load('scope');
        
        // Build author object (Unity expects an object, not a string)
        $author = [];
        if ($package->scope?->display_name) {
            $author['name'] = $package->scope->display_name;
        }
        
        // Build repository object (Unity expects an object with type and url)
        $repository = null;
        // TODO: Make repository configurable from package data
        // For now, we'll omit it if not available
        
        // Get dependencies from the release, inheriting from ancestor if needed
        $dependencies = $this->getDependenciesForRelease($release, $package);
        
        $packageJson = [
            'name' => $package->bundle_id,
            'version' => $version,
            'displayName' => $package->product_name ?? $package->bundle_id,
            'description' => $package->description ?? '',
            'keywords' => ['unity'],
            'dependencies' => $dependencies, // Always include dependencies (even if empty object)
        ];
        
        // Add author only if we have a name
        if (!empty($author)) {
            $packageJson['author'] = $author;
        }
        
        // Add repository only if configured (for now, we'll skip it)
        // $packageJson['repository'] = $repository;

        file_put_contents($packageJsonPath, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        Log::info('Created package.json', [
            'bundle_id' => $package->bundle_id,
            'package_json_path' => $packageJsonPath,
            'package_json' => $packageJson,
        ]);
    }

    /**
     * Get dependencies for a release as a map of bundle_id => version
     * If the release has no dependencies, inherits from ancestor releases.
     * 
     * @param Release $release The release model
     * @param Package $package The package model (for finding ancestor releases)
     * @return array Map of bundle_id => version (always returns an array, may be empty)
     */
    private function getDependenciesForRelease(Release $release, Package $package): array
    {
        $dependencies = [];
        
        // Load dependencies for this release
        $release->load('dependencies');
        
        // Get dependencies from current release
        foreach ($release->dependencies as $dep) {
            // Prefer bundle_id and version fields if they exist (newer approach)
            if (!empty($dep->bundle_id) && !empty($dep->version)) {
                $dependencies[$dep->bundle_id] = $dep->version;
            } 
            // Fallback to dependencyRelease relationship (older approach)
            elseif ($dep->dependencyRelease) {
                $dep->load('dependencyRelease.package');
                $depPackage = $dep->dependencyRelease->package;
                if ($depPackage && $depPackage->bundle_id) {
                    $dependencies[$depPackage->bundle_id] = $dep->dependencyRelease->version;
                }
            }
            // Handle external dependencies
            elseif ($dep->external_dependency) {
                // For external dependencies, we might not have a version
                // Use '*' as a placeholder or skip if version is required
                // Unity package manager requires specific versions, so we'll skip external deps without versions
                if (!empty($dep->version)) {
                    $dependencies[$dep->external_dependency] = $dep->version;
                }
            }
        }
        
        // If current release has no dependencies, inherit from ancestor release
        if (empty($dependencies)) {
            $ancestorRelease = $package->findAncestorReleaseWithReferences($release);
            if ($ancestorRelease) {
                $ancestorRelease->load('dependencies');
                Log::info('Inheriting dependencies from ancestor release', [
                    'current_release_id' => $release->id,
                    'current_release_version' => $release->version,
                    'ancestor_release_id' => $ancestorRelease->id,
                    'ancestor_release_version' => $ancestorRelease->version,
                ]);
                
                // Get dependencies from ancestor release
                foreach ($ancestorRelease->dependencies as $dep) {
                    // Prefer bundle_id and version fields if they exist (newer approach)
                    if (!empty($dep->bundle_id) && !empty($dep->version)) {
                        $dependencies[$dep->bundle_id] = $dep->version;
                    } 
                    // Fallback to dependencyRelease relationship (older approach)
                    elseif ($dep->dependencyRelease) {
                        $dep->load('dependencyRelease.package');
                        $depPackage = $dep->dependencyRelease->package;
                        if ($depPackage && $depPackage->bundle_id) {
                            $dependencies[$depPackage->bundle_id] = $dep->dependencyRelease->version;
                        }
                    }
                    // Handle external dependencies
                    elseif ($dep->external_dependency) {
                        if (!empty($dep->version)) {
                            $dependencies[$dep->external_dependency] = $dep->version;
                        }
                    }
                }
                
                if (!empty($dependencies)) {
                    Log::info('Inherited dependencies from ancestor', [
                        'ancestor_version' => $ancestorRelease->version,
                        'inherited_dependencies' => $dependencies,
                    ]);
                }
            }
        }
        
        return $dependencies;
    }

    /**
     * Validate folder structure and log non-standard items
     * Only checks root level - subdirectories are allowed to have any structure
     * 
     * @param string $dir Root directory to check
     * @param string $bundleId Bundle ID for logging
     */
    private function validateAndLogStructure(string $dir, string $bundleId): void
    {
        $nonStandardItems = [];
        $items = scandir($dir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $itemPath = $dir . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($itemPath);
            
            // Check if it's a standard directory at root level
            if ($isDir && !in_array($item, self::STANDARD_DIRS)) {
                $nonStandardItems[] = [
                    'type' => 'directory',
                    'name' => $item,
                    'relative_path' => $item,
                ];
            }
            
            // Check if it's a standard file at root level
            // Note: package.json and CHANGELOG.md are created by us, so they're expected
            if (!$isDir && !in_array($item, self::STANDARD_FILES)) {
                $nonStandardItems[] = [
                    'type' => 'file',
                    'name' => $item,
                    'relative_path' => $item,
                ];
            }
        }
        
        if (!empty($nonStandardItems)) {
            Log::warning('Non-standard items found in package structure', [
                'bundle_id' => $bundleId,
                'non_standard_items' => $nonStandardItems,
            ]);
        } else {
            Log::info('Package structure validation passed', [
                'bundle_id' => $bundleId,
            ]);
        }
    }

    /**
     * Create CHANGELOG.md file
     * 
     * @param string $dir Directory where CHANGELOG.md should be created
     * @param Package $package The package model
     */
    private function createChangelog(string $dir, Package $package): void
    {
        $changelogPath = $dir . DIRECTORY_SEPARATOR . 'CHANGELOG.md';
        
        // Get all releases for this package, ordered from newest to oldest
        $releases = Release::where('package_id', $package->id)
            ->whereNotNull('changelog')
            ->where('changelog', '!=', '')
            ->orderBy('create_time', 'desc')
            ->orderBy('id', 'desc')
            ->get(['version', 'changelog']);
        
        $changelogContent = '';
        
        foreach ($releases as $release) {
            if (!empty(trim($release->changelog))) {
                $changelogContent .= "Version: {$release->version}\n\n";
                $changelogContent .= "========\n\n";
                $changelogContent .= trim($release->changelog) . "\n\n\n";
            }
        }
        
        // Remove trailing newlines
        $changelogContent = rtrim($changelogContent);
        
        file_put_contents($changelogPath, $changelogContent);
        
        Log::info('Created CHANGELOG.md', [
            'changelog_path' => $changelogPath,
            'package_id' => $package->id,
            'releases_count' => $releases->count(),
        ]);
    }

    /**
     * Add README.md from incoming folder to the package
     * 
     * @param string $dir The temp directory where the package is being assembled
     * @param Package $package The package model
     */
    private function addPackageReadme(string $dir, Package $package): void
    {
        $readmePath = "incoming/{$package->bundle_id}/README.md";
        
        if (!Storage::disk('local')->exists($readmePath)) {
            Log::warning('README.md not found in incoming folder', [
                'readme_path' => $readmePath,
                'package_id' => $package->id,
                'bundle_id' => $package->bundle_id,
            ]);
            return;
        }
        
        $targetPath = $dir . DIRECTORY_SEPARATOR . 'README.md';
        $readmeContent = Storage::disk('local')->get($readmePath);
        
        file_put_contents($targetPath, $readmeContent);
        
        Log::info('Added README.md from incoming folder', [
            'readme_path' => $readmePath,
            'target_path' => $targetPath,
            'package_id' => $package->id,
            'bundle_id' => $package->bundle_id,
            'file_size' => strlen($readmeContent),
        ]);
    }

    /**
     * Create a tarball from a directory
     * 
     * @param string $dir Directory to create tarball from
     * @param string $bundleId Bundle ID for filename
     * @param string $version Version for filename
     * @return array Returns ['path' => tarball path, 'filename' => tarball filename]
     */
    private function createTarball(string $dir, string $bundleId, string $version): array
    {
        $date = Carbon::now()->format('Y-m-d');
        $safeDate = preg_replace('/[^a-zA-Z0-9._-]/', '-', $date);
        $safeBundleId = preg_replace('/[^a-zA-Z0-9._-]/', '_', $bundleId);
        $safeVersion = preg_replace('/[^a-zA-Z0-9._-]/', '_', $version);
        $filename = "{$safeBundleId}-{$safeVersion}-{$safeDate}.tgz";
        
        $tempTarballPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        
        // Create tarball using tar command (Windows compatible approach)
        $this->createTarGz($dir, $tempTarballPath);
        
        Log::info('Created tarball', [
            'tarball_filename' => $filename,
            'tarball_temp_path' => $tempTarballPath,
        ]);

        return [
            'path' => $tempTarballPath,
            'filename' => $filename,
        ];
    }

    /**
     * Create a tar.gz archive (saved with .tgz extension)
     * 
     * @param string $sourceDir Source directory
     * @param string $outputFile Output file path (should end with .tgz)
     */
    private function createTarGz(string $sourceDir, string $outputFile): void
    {
        // Use PHP's PharData for cross-platform compatibility
        // First create the tar file (without .gz or .tgz)
        $tarFile = str_replace('.tgz', '.tar', $outputFile);
        $tarFile = str_replace('.tar.gz', '.tar', $tarFile);
        $tarFile = str_replace('.gz', '', $tarFile);
        
        $phar = new \PharData($tarFile);
        
        // Use buildFromIterator to ensure files are in package/ folder
        // Unity packages should have all files inside a 'package' root folder
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $sourceDir,
                \RecursiveDirectoryIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        // Build from iterator, stripping the source directory path
        // This ensures files appear at root level in the tarball (npm/Unity requirement)
        $sourceDirRealPath = realpath($sourceDir);
        if (!$sourceDirRealPath) {
            throw new \RuntimeException("Cannot resolve real path for source directory: {$sourceDir}");
        }
        
        $sourceDirNormalized = rtrim(str_replace('\\', '/', $sourceDirRealPath), '/');
        
        // First pass: collect all files with their paths
        $filesToAdd = [];
        
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue; // Skip directories
            }
            
            $filePath = $file->getRealPath();
            if (!$filePath) {
                continue; // Skip if can't resolve path
            }
            
            // Get relative path from source directory
            $filePathNormalized = str_replace('\\', '/', $filePath);
            
            // Ensure we properly strip the source directory
            if (strpos($filePathNormalized, $sourceDirNormalized) === 0) {
                $relativePath = substr($filePathNormalized, strlen($sourceDirNormalized) + 1);
            } else {
                // Fallback: try string replace
                $relativePath = str_replace($sourceDirNormalized . '/', '', $filePathNormalized);
            }
            
            // Ensure relative path doesn't start with /
            $relativePath = ltrim($relativePath, '/');
            
            // Skip if relative path is empty (shouldn't happen)
            if (empty($relativePath)) {
                continue;
            }
            
            // Wrap all files inside a 'package' root folder
            $relativePath = 'package/' . $relativePath;
            
            $filesToAdd[] = [
                'path' => $filePath,
                'relative' => $relativePath,
            ];
        }
        
        // Sort files: package/package.json first, then other package root files, then subdirectories
        usort($filesToAdd, function($a, $b) {
            // package/package.json always comes first
            if ($a['relative'] === 'package/package.json') return -1;
            if ($b['relative'] === 'package/package.json') return 1;
            
            // Files directly in package/ come before files in package subdirectories
            $aIsPackageRoot = preg_match('/^package\/[^\/]+$/', $a['relative']);
            $bIsPackageRoot = preg_match('/^package\/[^\/]+$/', $b['relative']);
            
            if ($aIsPackageRoot && !$bIsPackageRoot) return -1;
            if (!$aIsPackageRoot && $bIsPackageRoot) return 1;
            
            // Within same level, sort alphabetically
            return strcmp($a['relative'], $b['relative']);
        });
        
        // Second pass: add files to tarball in sorted order
        $fileCount = 0;
        $packageRootFiles = [];
        $allFiles = [];
        
        foreach ($filesToAdd as $fileInfo) {
            $phar->addFile($fileInfo['path'], $fileInfo['relative']);
            $fileCount++;
            $allFiles[] = $fileInfo['relative'];
            
            // Track files directly in package/ folder for logging
            if (preg_match('/^package\/[^\/]+$/', $fileInfo['relative'])) {
                $packageRootFiles[] = $fileInfo['relative'];
            }
        }
        
        Log::info('Tarball created with files in package/ folder', [
            'total_files' => $fileCount,
            'package_root_files' => $packageRootFiles,
            'has_package_json' => in_array('package/package.json', $packageRootFiles),
            'all_files_sample' => array_slice($allFiles, 0, 20), // First 20 files for debugging
            'source_dir' => $sourceDir,
            'source_dir_normalized' => $sourceDirNormalized,
        ]);
        
        // Verify package.json is in the tarball before compression
        if (!in_array('package/package.json', $packageRootFiles)) {
            Log::error('package.json not found in tarball package/ folder', [
                'package_root_files' => $packageRootFiles,
                'all_files' => $allFiles,
            ]);
            throw new \RuntimeException('package.json is not in package/ folder in tarball');
        }
        
        // Verify package.json exists in source directory (we already verified it exists before this method)
        // The tarball structure verification will happen after compression via verifyTarballStructure()
        
        // Compress to .gz (this creates a new file with .gz extension)
        $phar->compress(\Phar::GZ);
        
        // The compressed file is at $tarFile . '.gz'
        $compressedFile = $tarFile . '.gz';
        
        // If the output file path doesn't match, rename it
        if ($compressedFile !== $outputFile && file_exists($compressedFile)) {
            if (file_exists($outputFile)) {
                unlink($outputFile);
            }
            rename($compressedFile, $outputFile);
        }
        
        // Verify the final compressed tarball is valid
        if (!file_exists($outputFile)) {
            throw new \RuntimeException("Compressed tarball file was not created: {$outputFile}");
        }
        
        $fileSize = filesize($outputFile);
        if ($fileSize === 0 || $fileSize === false) {
            throw new \RuntimeException("Compressed tarball file is empty: {$outputFile}");
        }
        
        Log::info('Tarball compression completed', [
            'output_file' => $outputFile,
            'file_size' => $fileSize,
            'file_size_kb' => round($fileSize / 1024, 2),
        ]);
        
        // Remove the uncompressed tar file
        if (file_exists($tarFile)) {
            unlink($tarFile);
        }
    }

    /**
     * Clean up temporary files and directories
     * 
     * @param string $tempDir Temporary directory to remove
     * @param string $tempTarball Temporary tarball file to remove
     */
    private function cleanup(string $tempDir, string $tempTarball): void
    {
        // Remove temporary directory
        if (is_dir($tempDir)) {
            $this->removeDirectory($tempDir);
        }
        
        // Remove temporary tarball
        if (file_exists($tempTarball)) {
            unlink($tempTarball);
        }
        
        Log::info('Cleaned up temporary files', [
            'temp_dir' => $tempDir,
            'temp_tarball_path' => $tempTarball,
        ]);
    }

    /**
     * Verify tarball structure by extracting it and checking for package.json at root
     */
    private function verifyTarballStructure(string $tarballPath, string $bundleId): void
    {
        $verifyTempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tarball-verify-' . uniqid();
        if (!mkdir($verifyTempDir, 0755, true)) {
            Log::warning('Failed to create verification temp directory', [
                'tarball_path' => $tarballPath,
                'verify_dir' => $verifyTempDir,
            ]);
            return; // Don't fail the whole process if verification fails
        }
        
        try {
            $phar = new \PharData($tarballPath);
            $phar->extractTo($verifyTempDir);
            
            // Check if package.json exists in package/ folder
            $packageJsonPath = $verifyTempDir . DIRECTORY_SEPARATOR . 'package' . DIRECTORY_SEPARATOR . 'package.json';
            $packageJsonExists = file_exists($packageJsonPath);
            
            // List root items (should contain 'package' folder)
            $rootItems = array_filter(scandir($verifyTempDir), function($item) {
                return $item !== '.' && $item !== '..';
            });
            
            // List items in package folder if it exists
            $packageFolderPath = $verifyTempDir . DIRECTORY_SEPARATOR . 'package';
            $packageItems = [];
            if (is_dir($packageFolderPath)) {
                $packageItems = array_filter(scandir($packageFolderPath), function($item) {
                    return $item !== '.' && $item !== '..';
                });
            }
            
            Log::info('Tarball structure verification', [
                'bundle_id' => $bundleId,
                'tarball_path' => $tarballPath,
                'package_json_exists' => $packageJsonExists,
                'root_items' => $rootItems,
                'package_folder_exists' => is_dir($packageFolderPath),
                'package_items' => $packageItems,
            ]);
            
            if (!$packageJsonExists) {
                Log::error('Tarball verification failed: package.json not found in package/ folder', [
                    'bundle_id' => $bundleId,
                    'tarball_path' => $tarballPath,
                    'root_items' => $rootItems,
                    'package_items' => $packageItems,
                ]);
                throw new \RuntimeException('Tarball verification failed: package.json not found in package/ folder');
            }
        } finally {
            // Clean up verification temp directory
            if (is_dir($verifyTempDir)) {
                $this->removeDirectory($verifyTempDir);
            }
        }
    }

    /**
     * Generate Unity .meta files for all files and folders
     * 
     * @param string $dir Directory to process
     * @param Package $package The package model
     */
    private function generateMetaFiles(string $dir, Package $package): void
    {
        Log::info('Generating Unity .meta files', [
            'package_id' => $package->id,
            'bundle_id' => $package->bundle_id,
            'dir' => $dir,
        ]);

        $basePath = realpath($dir);
        if (!$basePath) {
            throw new \RuntimeException("Cannot resolve real path for directory: {$dir}");
        }

        $basePathNormalized = rtrim(str_replace('\\', '/', $basePath), '/');
        $metaFilesCreated = 0;
        $metaFilesSkipped = 0;

        // Recursively process all files and directories
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $basePath,
                \RecursiveDirectoryIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getRealPath();
            if (!$itemPath) {
                continue;
            }

            $itemPathNormalized = str_replace('\\', '/', $itemPath);
            
            // Get relative path from base directory
            $relativePath = substr($itemPathNormalized, strlen($basePathNormalized) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);
            
            // Skip if already a .meta file
            if (basename($relativePath) === '.meta' || str_ends_with($relativePath, '.meta')) {
                continue;
            }

            // Determine if this is a file or directory
            $isDir = $item->isDir();
            $metaPath = $itemPath . '.meta';

            // Skip if .meta file already exists
            if (file_exists($metaPath)) {
                $metaFilesSkipped++;
                Log::debug('Skipping existing .meta file', [
                    'meta_path' => $metaPath,
                    'relative_path' => $relativePath,
                ]);
                continue;
            }

            // Get or create GUID for this file/folder
            $metaFile = MetaFile::where('package_id', $package->id)
                ->where('relative_path', $relativePath)
                ->first();

            if (!$metaFile) {
                // Generate new GUID
                $guid = MetaFile::generateGuid();
                
                // Store in database
                $metaFile = MetaFile::create([
                    'package_id' => $package->id,
                    'relative_path' => $relativePath,
                    'guid' => $guid,
                ]);
                
                Log::debug('Created new meta file record', [
                    'relative_path' => $relativePath,
                    'guid' => $guid,
                ]);
            } else {
                Log::debug('Reusing existing GUID', [
                    'relative_path' => $relativePath,
                    'guid' => $metaFile->guid,
                ]);
            }

            // Create .meta file content
            $metaContent = "fileFormatVersion: 2\n";
            $metaContent .= "guid: {$metaFile->guid}\n";
            $metaContent .= "TextScriptImporter:\n";
            $metaContent .= "  externalObjects: {}\n";
            $metaContent .= "  userData: \n";
            $metaContent .= "  assetBundleName: \n";
            $metaContent .= "  assetBundleVariant: \n";

            // Write .meta file
            if (file_put_contents($metaPath, $metaContent) === false) {
                Log::warning('Failed to write .meta file', [
                    'meta_path' => $metaPath,
                    'relative_path' => $relativePath,
                ]);
            } else {
                $metaFilesCreated++;
            }
        }

        Log::info('Unity .meta files generation completed', [
            'package_id' => $package->id,
            'bundle_id' => $package->bundle_id,
            'meta_files_created' => $metaFilesCreated,
            'meta_files_skipped' => $metaFilesSkipped,
        ]);
    }

    /**
     * Recursively remove a directory
     * 
     * @param string $dir Directory to remove
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
