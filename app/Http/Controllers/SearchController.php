<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Package;
use App\Models\Release;
use App\Models\ReleaseArtifact;
use App\Models\PackageDependency;
use App\Models\DownloadHistory;
use App\Enums\ReleaseStatus;
use App\Enums\PackageStatus;

class SearchController extends Controller
{

    public function searchWithTrash(Request $request, $trash)
    {
        return $this->search($request);
    }
    public function search(Request $request)
    {
       
        $searchText = $request->input('text', '');

           
        // Query packages based on search text
        $query = Package::with(['releases' => function($q) {
            $q->orderBy('create_time', 'desc');
        }, 'creator', 'releases.artifacts']);
        
        // Filter by search text (searches in bundle_id and description)


        if (!config('app.ignore_scope_when_searching') && $searchText) {
            $query->where(function($q) use ($searchText) {
                $q->where('bundle_id', 'like', "%{$searchText}%")
                  ->orWhere('description', 'like', "%{$searchText}%");
            });
        }
        
        // Only get published packages
        $query->where('status', PackageStatus::PUBLISHED)
              ->where('disabled', false);
        
        // Pagination
        $packages = $query->get();


        Log::info("SearchController Search", ['searchText' => $searchText,'returned package count' => $packages?->count()]);
        // Build JSON response programmatically (similar to StringBuilder in C#)
        // 
        // This code produces the following JSON structure:
        // {
        //     "objects": [
        //         {
        //             "downloads": {
        //                 "monthly": 4695,
        //                 "weekly": 1088
        //             },
        //             "dependents": 
        //             "updated":
        //             "package": {
        //                 "name": "com.example",
        //                 "keywords": ["unity"],  // Optional - uncomment if stored
        //                 "version": "1.0.2",
        //                 "description": "Extensions for Unity standard gradient class",
        //                 "sanitized_name": "com-zamb-klak-lineargradient",
        //                 "publisher": {
        //                     "email": "keijiro@gmail.com",
        //                     "username": "keijiro"
        //                 },
        //                 "maintainers": [
        //                     {
        //                         "email": "keijiro@gmail.com",
        //                         "username": "keijiro"
        //                     }
        //                 ],
        //                 "license": "Unlicense",  // Optional - uncomment if stored
        //                 "date": "2020-09-17T05:21:55.349Z",
        //                 "links": {  // Optional - uncomment if stored
        //                     "homepage": "https://github.com/...",
        //                     "repository": "git+https://github.com/...",
        //                     "bugs": "https://github.com/.../issues",
        //                     "npm": "https://www.npmjs.com/package/..."
        //                 }
        //             },
        //             "score": {
        //                 "final": 114.680534,
        //                 "detail": {
        //                     "popularity": 1.0,
        //                     "quality": 1.0,
        //                     "maintenance": 1.0
        //                 }
        //             },
        //             "flags": {
        //                 "insecure": 0
        //             }
        //         }
        //     ]
        // }
        
        $json = [];
        $json['objects'] = [];
     
        
        foreach ($packages as $package) {
            // Get latest release
            $latestRelease = $package->releases->first();
            if (!$latestRelease || !$latestRelease->version) {
                continue; // Skip packages without releases
            }
            
            // Get package creator/maintainer
            $maintainer = $package->creator;
            
            // Build search result object
            $object = [];
          
            
            // Dependents count (packages that depend on this one)
            // Count distinct packages that have dependencies on this package's releases
            $dependentsCount = PackageDependency::whereHas('dependencyRelease', function($q) use ($package) {
                $q->where('package_id', $package->id);
            })->with('release.package')
              ->get()
              ->pluck('release.package_id')
              ->unique()
              ->count();
            $object['dependents'] = (string) $dependentsCount;
            
            // Updated timestamp
            $updatedTime = $latestRelease->update_time ?? $latestRelease->create_time;
            $object['updated'] = $updatedTime ? str_replace('+00:00', 'Z', $updatedTime->toIso8601String()) : now()->toIso8601String();
            
            // Search score (simple scoring - can be enhanced)
            
            // Package information
            $object['package'] = [];
            $object['package']['name'] = $package->bundle_id;
            // $object['package']['keywords'] = ['unity']; // Uncomment if you store keywords
            $object['package']['version'] = $latestRelease->version;
            $object['package']['description'] = $package->description ?? '';
            
            // Sanitized name (convert dots and special chars to hyphens)
            $object['package']['sanitized_name'] = str_replace(['.', '_'], '-', strtolower($package->bundle_id));
            
            
            // Date (latest release date)
            $object['package']['date'] = $latestRelease->create_time 
                ? str_replace('+00:00', 'Z', $latestRelease->create_time->toIso8601String())
                : now()->toIso8601String();
            
            // Links (repository and homepage)
            $object['package']['links'] = [];
            $object['package']['links']['homepage'] = $package->homepage_url ?? '';
            $object['package']['links']['repository'] = $package->repository_url ?? '';
        
            
            $json['objects'][] = $object;
        }
        
        // Convert to JSON string
        $jsonString = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        return response()->make($jsonString, 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Content-Type', 'application/json');
    } 
     
    
}