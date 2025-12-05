# npm-unity-server

A minimal Laravel-based npm registry server implementation for Unity3D package manager.

## Overview

This server implements a minimal subset of the npm registry protocol to allow Unity3D's package manager to discover and list available packages. It provides two endpoints:

- `/-/v1/search` - Search endpoint for package discovery
- `/-/all` - Returns all available packages

## Setup

This project is designed to run in XAMPP's `htdocs` directory.

### Access URLs

Since the project is in `htdocs/npm-unity-server`, access the endpoints via:

- Search: `http://localhost/npm-unity-server/public/-/v1/search`
- All packages: `http://localhost/npm-unity-server/public/-/all`

### Unity3D Configuration

In Unity3D, add this as a scoped registry:

1. Open **Edit** > **Project Settings** > **Package Manager**
2. Under **Scoped Registries**, click the **+** button
3. Configure:
   - **Name**: Local NPM Server (or any name)
   - **URL**: `http://localhost/npm-unity-server/public`
   - **Scopes**: `com.example` (or your desired scope)

## Dummy Packages

The server currently includes two dummy packages:

1. **unity-test-package** (versions: 1.0.0, 1.1.0)
2. **unity-helper-tools** (version: 2.0.0)

These can be modified in `app/Http/Controllers/NpmRegistryController.php` in the `getDummyPackages()` method.

## Requirements

- PHP 8.2+
- Composer
- XAMPP (or any Apache server with mod_rewrite)

## Notes

- This is a read-only implementation - no package publishing/editing capabilities
- All package data is hardcoded in the controller
- Designed for local development/testing only
