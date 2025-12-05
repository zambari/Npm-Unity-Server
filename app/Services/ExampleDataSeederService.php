<?php

namespace App\Services;

use App\Models\User;
use App\Models\Scope;
use App\Models\Package;
use App\Models\Release;
use App\Enums\ReleaseStatus;
use App\Enums\Channel;
use App\Enums\PackageStatus;
use Illuminate\Support\Facades\DB;

class ExampleDataSeederService
{
    /**
     * Generate a fragment name using prefixes and suffixes
     */
    private function generateFragmentName(): string
    {
        $prefixes = [
            'neon', 'mythos', 'shadow', 'cyber',
            'rune', 'sigil', 'ember', 'arc',
            'void', 'hex', 'lumen', 'astral'
        ];

        $suffixes = [
            'forge', 'synth', 'spire', 'mesh',
            'core', 'loom', 'nexus', 'shard',
            'flux', 'weave', 'gate', 'engine'
        ];

        $p = $prefixes[array_rand($prefixes)];
        $s = $suffixes[array_rand($suffixes)];

        return $p . $s;
    }

    /**
     * Generate an adjective for names
     */
    private function generateAdjective(): string
    {
        $adjectives = [
            'super', 'mega', 'awesome', 'epic',
            'ultra', 'pro', 'prime', 'elite',
            'advanced', 'premium', 'ultimate', 'max'
        ];

        return $adjectives[array_rand($adjectives)];
    }

    /**
     * Bump a semantic version randomly
     */
    private function bumpVersion(string $version): string
    {
        $parts = explode('.', $version);
        $major = (int)($parts[0] ?? 0);
        $minor = (int)($parts[1] ?? 0);
        $patch = (int)($parts[2] ?? 0);

        $bumpType = rand(1, 3); // 1 = patch, 2 = minor, 3 = major
        
        switch ($bumpType) {
            case 1:
                $patch++;
                break;
            case 2:
                $minor++;
                $patch = 0;
                break;
            case 3:
                $major++;
                $minor = 0;
                $patch = 0;
                break;
        }

        return "{$major}.{$minor}.{$patch}";
    }

    /**
     * Seed the database with example data
     */
    public function seed(int $numCategories, int $numPackages, int $numReleases, ?string $baseScope = null): array
    {
        $results = [
            'users_created' => 0,
            'scopes_created' => 0,
            'packages_created' => 0,
            'releases_created' => 0,
        ];

        DB::beginTransaction();
        try {
            // Use provided base scope or fall back to config
            $baseScope = $baseScope ?? config('app.default_scope', 'com.example');
            
            // Create default user if no users exist
            if (User::count() === 0) {
                $user = User::create([
                    'name' => 'Default User',
                    'email' => 'mail@' . $baseScope,
                    'password' => 'password', // Will be hashed by the model
                    'disabled' => false,
                ]);
                $results['users_created'] = 1;
                $defaultUser = $user;
            } else {
                $defaultUser = User::first();
            }

            // Adjust y if needed
            if ($numPackages <= $numCategories) {
                $numPackages = $numCategories * 2;
            }

            // Adjust z if needed
            if ($numReleases < 1) {
                $numReleases = 1;
            } elseif ($numReleases > 5) {
                $numReleases = 5;
            }

            $scopes = [];

            // Create scopes (categories)
            for ($i = 0; $i < $numCategories; $i++) {
                $name = $this->generateFragmentName();
                $adjective = $this->generateAdjective();
                $scopeId = $baseScope . '.' . $name;
                
                // Make sure scope doesn't already exist
                while (Scope::where('scope', $scopeId)->exists()) {
                    $name = $this->generateFragmentName();
                    $scopeId = $baseScope . '.' . $name;
                }

                $displayName = strtoupper($adjective) . ' ' . strtoupper($name);

                $scope = Scope::create([
                    'scope' => $scopeId,
                    'display_name' => $displayName,
                ]);

                $scopes[] = $scope;
                $results['scopes_created']++;
            }

            // Create packages
            for ($i = 0; $i < $numPackages; $i++) {
                $name = $this->generateFragmentName();
                $adjective = $this->generateAdjective();
                
                // Pick a random scope
                $scope = $scopes[array_rand($scopes)];
                
                // Create bundle_id
                $bundleId = $scope->scope . '.' . $name . 'Bundle';
                
                // Make sure bundle_id doesn't already exist
                while (Package::where('bundle_id', $bundleId)->exists()) {
                    $name = $this->generateFragmentName();
                    $bundleId = $scope->scope . '.' . $name . 'Bundle';
                }

                $productName = strtoupper($adjective) . ' ' . strtoupper($name);

                $package = Package::create([
                    'bundle_id' => $bundleId,
                    'product_name' => $productName,
                    'description' => "Example package: {$productName}",
                    'status' => PackageStatus::PUBLISHED,
                    'disabled' => false,
                    'created_by' => $defaultUser->id,
                    'scope_id' => $scope->id,
                ]);

                // Create releases for this package
                $currentVersion = '0.0.0';
                for ($j = 0; $j < $numReleases; $j++) {
                    // Bump version randomly
                    if ($j > 0) {
                        $currentVersion = $this->bumpVersion($currentVersion);
                    }

                    // Generate changelog
                    $changelogLines = [];
                    $numChangelogLines = rand(2, 5);
                    for ($k = 0; $k < $numChangelogLines; $k++) {
                        $adj = $this->generateAdjective();
                        $changelogLines[] = " - Updated {$adj} functionality";
                    }
                    $changelog = implode("\n", $changelogLines);

                    // Random channel and status
                    $channel = rand(0, 1) === 0 ? Channel::PUBLIC : Channel::BETA;
                    $status = rand(0, 10) < 9 ? ReleaseStatus::PUBLISHED : ReleaseStatus::UNPUBLISHED;

                    Release::create([
                        'package_id' => $package->id,
                        'version' => $currentVersion,
                        'channel' => $channel,
                        'release_status' => $status,
                        'changelog' => $changelog,
                        'user_id' => $defaultUser->id,
                        'create_time' => now()->subDays(rand(0, 30)),
                        'update_time' => now()->subDays(rand(0, 30)),
                    ]);

                    $results['releases_created']++;
                }

                $results['packages_created']++;
            }

            DB::commit();
            return $results;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
