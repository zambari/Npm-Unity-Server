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

class NpmConst extends Controller
{
    public function search(Request $request)
    {
        Log::info("NPM Search  {$request->path()} {$request->input('text', '')} {$request->input('from', 0)}");
      
        // Read JSON file from resources/temp
        $jsonPath = resource_path('temp/search.json');
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            return response()->make($jsonContent, 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Content-Type', 'application/json');
        } else {
            Log::info("NPM Search NOT FOUND file: $jsonPath");

            return null;
        }
    }
    
    public function getPackaget(Request $request,$trash, $bundle_id)
    {
 Log::info("Filtering out trash $trash from bundle_id $bundle_id");
        $parts = explode('/', $bundle_id);
        $bundle_id = end($parts);

        return $this->getPackage($request,$bundle_id);
    }
        
    public function getPackage(Request $request, $bundle_id)
    {
        // ===== TEMPORARY BLOCK - START =====
        // Map bundle_id to JSON filename
    
        $jsonFilename = $bundle_id . '.json';
        // Handle special case: com.zamb.package -> com.zamb.package2.json
        // if ($bundle_id === 'com.zamb.package') {
        //     $jsonFilename = 'com.zamb.package2.json';
        // }
        Log::info("get packgage $jsonFilename", [
            'full_url' => $request->fullUrl(),
            'query_params' => $request->query()
        ]);
        $jsonPath = resource_path('temp/' . $jsonFilename);
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            Log::info("returning $jsonPath");
            // if ($jsonData !== null && $jsonData !== []) {
            return response()->make($jsonContent, 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                // ->header('Access-Control-Allow-Headers', 'Content-Type')      
                ->header('Content-Type', 'application/json');
        } else  // Log incoming request details
        {
            Log::info("get package not found $jsonFilename");
        }

        return null;
    }
// likely not needed to implement it anyways
    public function allPackages(Request $request)
    {
        // ===== TEMPORARY BLOCK - START =====
        Log::info("NPM CONST All Packages  {$request->path()}", [
            'full_url' => $request->fullUrl(),
            'query_params' => $request->query()
        ]);
        // Read JSON file from resources/temp
        $jsonPath = resource_path('temp/all.json');
        if (file_exists($jsonPath)) {

            $jsonContent = file_get_contents($jsonPath);
            Log::info('Responding to ALL query');
            return response()->make($jsonContent, 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                // ->header('Access-Control-Allow-Headers', 'Content-Type')
                ->header('Content-Type', 'application/json');;
        } else  // Log incoming request details
        {
            Log::info('could not load file all.json');
            return;
        }
    }
}
