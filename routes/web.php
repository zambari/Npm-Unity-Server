<?php

use Illuminate\Http\Request;
use App\Http\Controllers\NpmConst;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\PackageResponseController;
// use App\Http\Controllers\NpmRegistryController;

Route::get('/', function () {
    $scopes = \App\Models\Scope::withCount('packages')->orderBy('scope', 'asc')->get();
    $totalPackages = $scopes->sum('packages_count');
    return view('welcome', compact('scopes', 'totalPackages'));
})->name('welcome');

Route::get('/loginform', [\App\Http\Controllers\AuthController::class, 'showLogin']);
Route::get('/login', [\App\Http\Controllers\AuthController::class, 'showLogin']);
Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login'])->name('login');
Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->name('logout');


// Package management routes (available for authenticated users)
Route::middleware(['admin'])->group(function () {
    Route::get('/packages', [\App\Http\Controllers\PackageListViewController::class, 'index'])->name('packages.index');
    Route::get('/packages/create', [\App\Http\Controllers\PackageListViewController::class, 'create'])->name('packages.create');
    Route::post('/packages', [\App\Http\Controllers\PackageListViewController::class, 'store'])->name('packages.store');
    Route::get('/packages/{package}', [\App\Http\Controllers\PackageListViewController::class, 'show'])->name('packages.show');
    Route::get('/packages/{package}/edit', [\App\Http\Controllers\PackageListViewController::class, 'edit'])->name('packages.edit');
    Route::put('/packages/{package}', [\App\Http\Controllers\PackageListViewController::class, 'update'])->name('packages.update');
    
    // Release management routes
    Route::get('/packages/{package}/releases/create', [\App\Http\Controllers\PackageListViewController::class, 'createRelease'])->name('packages.releases.create');
    Route::post('/packages/{package}/releases', [\App\Http\Controllers\PackageListViewController::class, 'storeRelease'])->name('packages.releases.store');
    Route::get('/packages/{package}/releases/{release}/edit', [\App\Http\Controllers\PackageListViewController::class, 'editRelease'])->name('packages.releases.edit');
    Route::put('/packages/{package}/releases/{release}', [\App\Http\Controllers\PackageListViewController::class, 'updateRelease'])->name('packages.releases.update');
    Route::delete('/packages/{package}/releases/{release}', [\App\Http\Controllers\PackageListViewController::class, 'destroyRelease'])->name('packages.releases.destroy');
    Route::get('/packages/{package}/releases/{release}/download', [\App\Http\Controllers\PackageListViewController::class, 'downloadArtifact'])->name('packages.releases.download');
    Route::get('/packages/{package}/releases/{release}/download-uploaded', [\App\Http\Controllers\PackageListViewController::class, 'downloadUploadedFile'])->name('packages.releases.download-uploaded');
    Route::get('/packages/{package}/releases/{release}/check-filename', [\App\Http\Controllers\PackageListViewController::class, 'checkFilename'])->name('packages.releases.check-filename');
    Route::get('/packages/{package}/releases/{release}/ancestor-references', [\App\Http\Controllers\PackageListViewController::class, 'getAncestorReferences'])->name('packages.releases.ancestor-references');
    Route::get('/packages/{package}/releases/{release}/inspect-tarball', [\App\Http\Controllers\PackageListViewController::class, 'inspectTarball'])->name('packages.releases.inspect-tarball');
    Route::post('/packages/{package}/releases/{release}/reprocess', [\App\Http\Controllers\PackageListViewController::class, 'reprocessRelease'])->name('packages.releases.reprocess');
    Route::post('/packages/{package}/releases/{release}/reprocess-new-version', [\App\Http\Controllers\PackageListViewController::class, 'reprocessReleaseWithNewVersion'])->name('packages.releases.reprocess-new-version');
});

// Database initialization endpoint (safe to call multiple times)
Route::get('/initializedb', [\App\Http\Controllers\DatabaseController::class, 'initialize'])->name('db.initialize');

// Static assets - must be before catch-all package routes
Route::get('/favicon.png', function () {
    return response()->file(public_path('favicon.png'));
});

// User management routes (super-user only)
Route::middleware(['superuser'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index'])->name('users');
    Route::post('/users', [\App\Http\Controllers\UserController::class, 'store'])->name('users.store');
    Route::post('/users/{id}/toggle-disabled', [\App\Http\Controllers\UserController::class, 'toggleDisabled'])->name('users.toggle-disabled');
    Route::post('/users/{id}/reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword'])->name('users.reset-password');
    
    // Scope management routes
    Route::get('/scopes', [\App\Http\Controllers\ScopeController::class, 'index'])->name('scopes');
    Route::post('/scopes', [\App\Http\Controllers\ScopeController::class, 'store'])->name('scopes.store');
    Route::get('/scopes/{id}/edit', [\App\Http\Controllers\ScopeController::class, 'edit'])->name('scopes.edit');
    Route::put('/scopes/{id}', [\App\Http\Controllers\ScopeController::class, 'update'])->name('scopes.update');
    Route::delete('/scopes/{id}', [\App\Http\Controllers\ScopeController::class, 'destroy'])->name('scopes.destroy');
    
    // Experiments routes (super-admin only)
    Route::get('/experiments', [\App\Http\Controllers\ExperimentsController::class, 'index'])->name('experiments');
    Route::post('/experiments/spawn-releases', [\App\Http\Controllers\ExperimentsController::class, 'spawnReleases'])->name('experiments.spawn-releases');
    Route::post('/experiments/define-dependencies', [\App\Http\Controllers\ExperimentsController::class, 'defineDependencies'])->name('experiments.define-dependencies');
    Route::get('/experiments/download-dump', [\App\Http\Controllers\ExperimentsController::class, 'downloadDump'])->name('experiments.download-dump');
    Route::post('/experiments/restore-dump', [\App\Http\Controllers\ExperimentsController::class, 'restoreDump'])->name('experiments.restore-dump');
    Route::post('/experiments/clear-data', [\App\Http\Controllers\ExperimentsController::class, 'clearData'])->name('experiments.clear-data');
    Route::post('/experiments/nuke-data', [\App\Http\Controllers\ExperimentsController::class, 'nukeData'])->name('experiments.nuke-data');
    Route::post('/experiments/delete-incoming-package', [\App\Http\Controllers\ExperimentsController::class, 'deleteIncomingPackage'])->name('experiments.delete-incoming-package');
    Route::post('/experiments/delete-all-incoming-but-latest', [\App\Http\Controllers\ExperimentsController::class, 'deleteAllIncomingButLatest'])->name('experiments.delete-all-incoming-but-latest');
    Route::post('/experiments/delete-processed-file', [\App\Http\Controllers\ExperimentsController::class, 'deleteProcessedFile'])->name('experiments.delete-processed-file');
    Route::post('/experiments/delete-all-processed-but-latest', [\App\Http\Controllers\ExperimentsController::class, 'deleteAllProcessedButLatest'])->name('experiments.delete-all-processed-but-latest');
    Route::post('/experiments/create-example-data', [\App\Http\Controllers\ExperimentsController::class, 'createExampleData'])->name('experiments.create-example-data');
});

// NPM Protocol endpoints for Unity3D package manager
// Route::get('/-/v1/search', [NpmRegistryController::class, 'search']);
// Route::get('/-/all', [NpmRegistryController::class, 'allPackages']);
// Route::get('/{packageName}', [NpmRegistryController::class, 'getPackage'])
//     ->where('packageName', '[^/]+');

// NPM Protocol endpoints for Unity3D package manager
// Note: Query parameters (e.g., ?sth=1&x=2) are automatically supported by Laravel
// and don't need to be defined in the route. They're accessible via $request->input() or $request->query()
// Example: /-/v1/search?sth=1&x=2 will match the route below and parameters are available in the controller
// Unity can add query parameters to force retries by making each URL unique

Route::get('/-/all', [NpmConst::class, 'allPackages']);


Route::get('/-/v1/search', [SearchController::class, 'search']);
Route::get('/{trash}/-/v1/search', [SearchController::class, 'searchWithTrash'])
    ->where('trash', '.*');

// Tarball download route - must be before package routes to avoid conflicts
Route::get('/{packageName}/download/{filename}', [PackageResponseController::class, 'downloadTarball'])
    ->where(['packageName' => '[^/]+', 'filename' => '[^/]+'])
    ->name('package.tarball');

Route::get('/{packageName}', [PackageResponseController::class, 'getPackage'])
    ->where('packageName', '[^/]+');
    
Route::get('/{trash}/{packageName}', [PackageResponseController::class, 'getPackageWithTrash'])
    ->where('trash', '.*')
    ->where('packageName', '[^/]+');

// // This wildcard to allow /xyx/-/all etc, so you can keep changing the part of the url in Untiy Preferences, 
// // that forces Untiy to actually reload packages, but we need to strip that part of the url and ignore it.
// Route::prefix('{any?}')->where(['any' => '.*'])->group(function () {
//     Route::get('/-/v1/search', [SearchController::class, 'search']);
//     Route::get('/-/all',       [NpmConst::class, 'allPackages']);
//     // Route::get('/{package}',   [NpmConst::class, 'getPackage']);
// });




// // Tarball download route
// Route::get('/{packageName}/-/{filename}.tgz', [NpmRegistryController::class, 'downloadTarball'])
//     ->where(['packageName' => '[^/]+', 'filename' => '[^/]+'])
//     ->name('package.tarball');

// // Individual package endpoint - must be after /-/ routes to avoid conflicts


// // Catch-all for any other requests Unity might make
// Route::any('{any}', function (Request $request) {
//     \Illuminate\Support\Facades\Log::info('Unhandled Request', [
//         'method' => $request->method(),
//         'url' => $request->fullUrl(),
//         'path' => $request->path(),
//         'query_params' => $request->all(),
//         'headers' => $request->headers->all(),
//     ]);
//     return response()->json(['error' => 'Endpoint not implemented'], 404);
// })->where('any', '.*');
