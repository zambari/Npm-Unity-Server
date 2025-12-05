<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NpmRegistryController extends Controller
{
    /**
     * Get dummy packages data
     */
    private function getDummyPackages()
    {
        return [
            'com.zamb.unity-test-package' => [
                'name' => 'com.zamb.unity-test-package',
                'description' => 'A test package for Unity3D package manager',
                'versions' => [
                    '1.0.0' => [
                        'name' => 'com.zamb.unity-test-package',
                        'version' => '1.0.0',
                        'description' => 'A test package for Unity3D package manager',
                        'dist' => [
                            'tarball' => url('/com.zamb.unity-test-package/-/com.zamb.unity-test-package-1.0.0.tgz'),
                            'shasum' => 'dummy-sha-sum',
                        ],
                        'maintainers' => [
                            [
                                'name' => 'test-maintainer',
                                'email' => 'test@example.com',
                            ],
                        ],
                        'repository' => [
                            'type' => 'git',
                            'url' => 'https://example.com/com.zamb.unity-test-package',
                        ],
                        'homepage' => 'https://example.com/com.zamb.unity-test-package',
                        'keywords' => ['unity', 'test'],
                        'author' => [
                            'name' => 'Test Author',
                            'email' => 'author@example.com',
                        ],
                        'license' => 'MIT',
                        'readme' => '# Unity Test Package\n\nA test package for Unity3D.',
                        'time' => now()->toIso8601String(),
                    ],
                    '1.1.0' => [
                        'name' => 'com.zamb.unity-test-package',
                        'version' => '1.1.0',
                        'description' => 'A test package for Unity3D package manager - updated',
                        'dist' => [
                            'tarball' => url('/com.zamb.unity-test-package/-/com.zamb.unity-test-package-1.1.0.tgz'),
                            'shasum' => 'dummy-sha-sum-2',
                        ],
                        'maintainers' => [
                            [
                                'name' => 'test-maintainer',
                                'email' => 'test@example.com',
                            ],
                        ],
                        'repository' => [
                            'type' => 'git',
                            'url' => 'https://example.com/com.zamb.unity-test-package',
                        ],
                        'homepage' => 'https://example.com/com.zamb.unity-test-package',
                        'keywords' => ['unity', 'test'],
                        'author' => [
                            'name' => 'Test Author',
                            'email' => 'author@example.com',
                        ],
                        'license' => 'MIT',
                        'readme' => '# Unity Test Package\n\nA test package for Unity3D - version 1.1.0.',
                        'time' => now()->toIso8601String(),
                    ],
                ],
                'dist-tags' => [
                    'latest' => '1.1.0',
                ],
                'time' => [
                    'created' => now()->subDays(30)->toIso8601String(),
                    '1.0.0' => now()->subDays(30)->toIso8601String(),
                    '1.1.0' => now()->subDays(5)->toIso8601String(),
                    'modified' => now()->subDays(5)->toIso8601String(),
                ],
                'maintainers' => [
                    [
                        'name' => 'test-maintainer',
                        'email' => 'test@example.com',
                    ],
                ],
                'author' => [
                    'name' => 'Test Author',
                    'email' => 'author@example.com',
                ],
                'repository' => [
                    'type' => 'git',
                    'url' => 'https://example.com/com.zamb.unity-test-package',
                ],
                'homepage' => 'https://example.com/com.zamb.unity-test-package',
                'keywords' => ['unity', 'test'],
                'license' => 'MIT',
            ],
            'com.zamb.helper-tools' => [
                'name' => 'com.zamb.helper-tools',
                'description' => 'Helper tools for Unity development',
                'versions' => [
                    '2.0.0' => [
                        'name' => 'com.zamb.helper-tools',
                        'version' => '2.0.0',
                        'description' => 'Helper tools for Unity development',
                        'dist' => [
                            'tarball' => url('/com.zamb.helper-tools/-/com.zamb.helper-tools-2.0.0.tgz'),
                            'shasum' => 'dummy-sha-sum-3',
                        ],
                        'maintainers' => [
                            [
                                'name' => 'helper-maintainer',
                                'email' => 'helper@example.com',
                            ],
                        ],
                        'repository' => [
                            'type' => 'git',
                            'url' => 'https://example.com/com.zamb.helper-tools',
                        ],
                        'homepage' => 'https://example.com/com.zamb.helper-tools',
                        'keywords' => ['unity', 'tools', 'helper'],
                        'author' => [
                            'name' => 'Helper Author',
                            'email' => 'helper-author@example.com',
                        ],
                        'license' => 'MIT',
                        'readme' => '# Unity Helper Tools\n\nUseful tools for Unity development.',
                        'time' => now()->toIso8601String(),
                    ],
                ],
                'dist-tags' => [
                    'latest' => '2.0.0',
                ],
                'time' => [
                    'created' => now()->subDays(15)->toIso8601String(),
                    '2.0.0' => now()->subDays(15)->toIso8601String(),
                    'modified' => now()->subDays(15)->toIso8601String(),
                ],
                'maintainers' => [
                    [
                        'name' => 'helper-maintainer',
                        'email' => 'helper@example.com',
                    ],
                ],
                'author' => [
                    'name' => 'Helper Author',
                    'email' => 'helper-author@example.com',
                ],
                'repository' => [
                    'type' => 'git',
                    'url' => 'https://example.com/com.zamb.helper-tools',
                ],
                'homepage' => 'https://example.com/com.zamb.helper-tools',
                'keywords' => ['unity', 'tools', 'helper'],
                'license' => 'MIT',
            ],
        ];
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
        
        $packages = $this->getDummyPackages();
        $results = [];

        foreach ($packages as $packageName => $packageData) {
            // If there's a search query, filter by name
            // Unity searches with scope like "com.zamb" and packages are "com.zamb.*"
            if ($query && stripos($packageName, $query) === false) {
                continue;
            }

            $latestVersion = $packageData['dist-tags']['latest'];
            $versionData = $packageData['versions'][$latestVersion];
            $updatedTime = $packageData['time']['modified'] ?? $packageData['time']['created'];
            // Convert to npm's format (Z instead of +00:00)
            $updatedTime = str_replace('+00:00', 'Z', $updatedTime);
            
            // Create sanitized name (convert dots to hyphens)
            $sanitizedName = str_replace('.', '-', $packageName);

            $results[] = [
                'downloads' => [
                    'monthly' => 0,
                    'weekly' => 0,
                ],
                'dependents' => 0,
                'updated' => $updatedTime,
                'searchScore' => 100.0,
                'package' => [
                    'name' => $packageName,
                    'version' => $latestVersion,
                    'description' => $packageData['description'],
                    'keywords' => $packageData['keywords'] ?? [],
                    'date' => $updatedTime,
                    'sanitized_name' => $sanitizedName,
                    'publisher' => [
                        'username' => $packageData['maintainers'][0]['name'] ?? 'unknown',
                        'email' => $packageData['maintainers'][0]['email'] ?? 'unknown@example.com',
                    ],
                    'maintainers' => array_map(function ($m) {
                        return [
                            'username' => $m['name'],
                            'email' => $m['email'],
                        ];
                    }, $packageData['maintainers'] ?? []),
                    'license' => $packageData['license'] ?? 'MIT',
                    'links' => [
                        'npm' => url('/' . $packageName),
                        'repository' => $packageData['repository']['url'] ?? '',
                        'homepage' => $packageData['homepage'] ?? '',
                    ],
                ],
                'score' => [
                    'final' => 100,
                    'detail' => [
                        'quality' => 1,
                        'popularity' => 1,
                        'maintenance' => 1,
                    ],
                ],
                'flags' => [
                    'insecure' => 0,
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

        $packages = $this->getDummyPackages();
        
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
    public function getPackage(Request $request, $packageName)
    {
        // Log incoming request details
        Log::info('NPM Individual Package Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'package_name' => $packageName,
            'query_params' => $request->all(),
            'headers' => [
                'user-agent' => $request->header('User-Agent'),
                'accept' => $request->header('Accept'),
                'content-type' => $request->header('Content-Type'),
            ],
            'ip' => $request->ip(),
        ]);

        $packages = $this->getDummyPackages();
        
        if (!isset($packages[$packageName])) {
            Log::warning('Package not found', ['package_name' => $packageName]);
            return response()->json(['error' => 'Package not found'], 404);
        }

        $packageData = $packages[$packageName];
        
        // Convert time fields to Z format
        $packageData['time'] = array_map(function($time) {
            return str_replace('+00:00', 'Z', $time);
        }, $packageData['time']);
        
        // Convert version time fields
        foreach ($packageData['versions'] as $version => &$versionData) {
            if (isset($versionData['time'])) {
                $versionData['time'] = str_replace('+00:00', 'Z', $versionData['time']);
            }
        }
        
        Log::info('NPM Individual Package Response', [
            'package_name' => $packageName,
            'versions' => array_keys($packageData['versions']),
        ]);
        
        return response()->json($packageData)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }
}

