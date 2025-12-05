<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Generate Default Version
    |--------------------------------------------------------------------------
    |
    | When enabled, packages without any uploaded versions will automatically
    | have a default version (0.0.0) created so they appear in Unity's package
    | manager. Set to false to disable this feature.
    |
    */

    'generate_default_version' => env('REGISTRY_GENERATE_DEFAULT_VERSION', true),

    /*
    |--------------------------------------------------------------------------
    | Default Version Number
    |--------------------------------------------------------------------------
    |
    | The version number to use for packages that have no uploaded versions.
    | This version will be marked as "unpublished" in the system.
    |
    */

    'default_version' => env('REGISTRY_DEFAULT_VERSION', '0.0.0'),
];
