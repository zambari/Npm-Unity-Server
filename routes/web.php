<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NpmRegistryController;

Route::get('/', function () {
    return view('welcome');
});

// NPM Protocol endpoints for Unity3D package manager
Route::get('/-/v1/search', [NpmRegistryController::class, 'search']);
Route::get('/-/all', [NpmRegistryController::class, 'allPackages']);

// Individual package endpoint - must be after /-/ routes to avoid conflicts
Route::get('/{packageName}', [NpmRegistryController::class, 'getPackage'])
    ->where('packageName', '[^/]+');

// Catch-all for any other requests Unity might make
Route::any('{any}', function (Request $request) {
    \Illuminate\Support\Facades\Log::info('Unhandled NPM Request', [
        'method' => $request->method(),
        'url' => $request->fullUrl(),
        'path' => $request->path(),
        'query_params' => $request->all(),
        'headers' => $request->headers->all(),
    ]);
    return response()->json(['error' => 'Endpoint not implemented'], 404);
})->where('any', '.*');
