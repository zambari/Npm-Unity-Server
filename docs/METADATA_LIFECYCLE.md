# Package Metadata Lifecycle

## Overview

This document describes how package metadata flows through the system from upload to Unity discovery.

## Architecture Layers

1. **PublishController** - HTTP layer, receives uploads
2. **PackagePublisher** (Service) - Business logic, orchestrates operations
3. **Operation Classes** - Perform specific tasks (validation, processing, etc.)
4. **Database** - Stores final metadata
5. **NpmRegistryController** - Read-only, serves data to Unity

## Metadata Flow

### 1. Upload Phase (PublishController → PackagePublisher)

```
User uploads tarball
    ↓
PublishController::publishVersion()
    ↓
PackagePublisher::publishVersion()
```

**Input:**
- Uploaded file (tarball)
- Options: `skip_validation`, `version`, `changelog`, `is_staging`

### 2. Processing Phase (PackagePublisher → Operations)

```
PackagePublisher receives artifact
    ↓
[Future: Operation classes]
    ├─ PackageValidator (validates package.json)
    ├─ ArtifactProcessor (extracts metadata)
    ├─ MetadataExtractor (extracts version, dependencies)
    └─ StorageManager (stores artifact)
```

**Current Operations:**
- **PackageValidator** - Validates Unity package structure, extracts package.json
- **Storage** - Stores tarball in `storage/app/packages/{bundle_id}/{version}.tgz`

### 3. Storage Phase (PackagePublisher → Database)

**Only happens if ALL operations succeed:**

```
Create PackageVersion record:
    - version (from package.json or manual input)
    - tarball_path (storage path)
    - changelog (user input)
    - dependencies (from package.json)
    - published_at (timestamp)
    - is_staging (default: false = public)

Update Package record:
    - latest_version (only if public AND newer than current)
    - author_name, description (from package.json if not set)
```

**Important:** Database is only updated AFTER all operations succeed. If any step fails, nothing is stored.

### 4. Serving Phase (NpmRegistryController → Unity)

```
Unity requests package list
    ↓
NpmRegistryController::getPackages()
    ↓
Filters: Only public versions (is_staging = false)
    ↓
Returns JSON to Unity
```

**What Unity sees:**
- Only public versions (staging versions are hidden)
- Latest version is the highest public version number
- All metadata from PackageVersion records

## Version States

### Staging (`is_staging = true`)
- **Not visible** to Unity
- Can be used for testing/preview
- Does NOT update `latest_version` field
- Can be promoted to public later

### Public (`is_staging = false`)
- **Visible** to Unity
- Updates `latest_version` if it's the newest
- Appears in npm registry endpoints

## Key Points

1. **NpmRegistryController is read-only** - Never modifies data
2. **PackagePublisher is transactional** - All or nothing (no partial saves)
3. **Staging versions are filtered** - Unity never sees them
4. **latest_version** - Always points to the highest public version
5. **Metadata source of truth** - Database (PackageVersion records)

## Current Issue

If you see version mismatch:
- **View shows:** All versions (including staging)
- **Unity sees:** Only public versions
- **Solution:** Check `is_staging` field - versions must be public (`is_staging = false`) to appear in Unity

