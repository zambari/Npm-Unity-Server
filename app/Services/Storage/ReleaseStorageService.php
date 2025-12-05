<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ReleaseStorageService
{
    /**
     * Store an uploaded file for a release
     * 
     * @param UploadedFile $file The uploaded file
     * @param string $bundleId The package bundle ID
     * @return array Returns ['path' => storage path, 'filename' => original filename, 'full_path' => full filesystem path]
     */
    public function storeReleaseFile(UploadedFile $file, string $bundleId): array
    {
        $date = Carbon::now()->format('Y-m-d');
        $originalFilename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filenameWithoutExt = pathinfo($originalFilename, PATHINFO_FILENAME);
        
        // Create a safe filename: sanitize and add timestamp to avoid collisions
        $safeFilename = $this->sanitizeFilename($filenameWithoutExt) . '_' . time() . '.' . $extension;
        
        // Storage path: incoming/{bundle_id}/{date}/{filename}
        $storagePath = "incoming/{$bundleId}/{$date}/{$safeFilename}";
        
        // Store the file
        $storedPath = $file->storeAs("incoming/{$bundleId}/{$date}", $safeFilename, 'local');
        $fullPath = $this->getFullPath($storedPath);
        
        // Verify the file was actually written
        $fileExists = file_exists($fullPath);
        $fileSize = $fileExists ? filesize($fullPath) : 0;
        
        if (!$fileExists) {
            Log::error('File storage failed - file does not exist after storeAs', [
                'bundle_id' => $bundleId,
                'relative_path' => $storedPath,
                'full_path' => $fullPath,
                'original_filename' => $originalFilename,
                'stored_filename' => $safeFilename,
                'storage_disk_root' => Storage::disk('local')->path(''),
            ]);
            throw new \RuntimeException("Failed to store file: {$fullPath}");
        }
        
        Log::info('File stored in incoming directory', [
            'bundle_id' => $bundleId,
            'relative_path' => $storedPath,
            'full_path' => $fullPath,
            'original_filename' => $originalFilename,
            'stored_filename' => $safeFilename,
            'file_exists' => $fileExists,
            'file_size_bytes' => $fileSize,
        ]);
        
        return [
            'path' => $storedPath,
            'filename' => $originalFilename,
            'stored_filename' => $safeFilename,
            'full_path' => $fullPath,
        ];
    }

    /**
     * Get the full storage path for a release artifact
     * 
     * @param string $relativePath The relative path from storage
     * @return string Full filesystem path
     */
    public function getFullPath(string $relativePath): string
    {
        return Storage::disk('local')->path($relativePath);
    }

    /**
     * Check if a file exists
     * 
     * @param string $relativePath The relative path from storage
     * @return bool
     */
    public function fileExists(string $relativePath): bool
    {
        return Storage::disk('local')->exists($relativePath);
    }

    /**
     * Delete a stored file
     * 
     * @param string $relativePath The relative path from storage
     * @return bool
     */
    public function deleteFile(string $relativePath): bool
    {
        return Storage::disk('local')->delete($relativePath);
    }

    /**
     * Sanitize filename to be filesystem-safe
     * 
     * @param string $filename
     * @return string
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove or replace unsafe characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        // Remove multiple consecutive underscores
        $filename = preg_replace('/_+/', '_', $filename);
        // Trim underscores from start/end
        $filename = trim($filename, '_');
        
        return $filename ?: 'file';
    }
}

