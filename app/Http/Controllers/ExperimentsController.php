<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Release;
use App\Models\PackageDependency;
use Illuminate\Http\Request;
use App\Enums\ReleaseStatus;
use App\Enums\Channel;
use App\Services\ExampleDataSeederService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class ExperimentsController extends Controller
{
    /**
     * Display the experiments page
     */
    public function index()
    {
        $storageStats = $this->getStorageStats();
        return view('admin.experiments.index', compact('storageStats'));
    }

    /**
     * Get storage statistics for incoming and processed directories
     */
    private function getStorageStats(): array
    {
        $incomingPath = storage_path('app/private/incoming');
        $processedPath = storage_path('app/private/incoming_processed');
        
        $incomingStats = $this->scanIncomingDirectory($incomingPath);
        $processedStats = $this->scanProcessedDirectory($processedPath);
        
        return [
            'incoming' => $incomingStats,
            'processed' => $processedStats,
        ];
    }

    /**
     * Scan incoming directory and group by bundle_id
     */
    private function scanIncomingDirectory(string $basePath): array
    {
        $totalFiles = 0;
        $totalSize = 0;
        $packages = [];
        
        if (!is_dir($basePath)) {
            return [
                'total_files' => 0,
                'total_size_kb' => 0,
                'packages' => [],
            ];
        }
        
        // Scan bundle_id folders
        $bundleDirs = array_filter(glob($basePath . '/*'), 'is_dir');
        
        foreach ($bundleDirs as $bundleDir) {
            $bundleId = basename($bundleDir);
            $packageFiles = 0;
            $packageSize = 0;
            $latestDate = null;
            $latestTimestamp = 0;
            
            // Scan date folders within bundle_id
            $dateDirs = array_filter(glob($bundleDir . '/*'), 'is_dir');
            foreach ($dateDirs as $dateDir) {
                $date = basename($dateDir);
                $dateTimestamp = filemtime($dateDir);
                
                if ($dateTimestamp > $latestTimestamp) {
                    $latestTimestamp = $dateTimestamp;
                    $latestDate = $date;
                }
                
                $files = array_filter(glob($dateDir . '/*'), 'is_file');
                foreach ($files as $file) {
                    $packageFiles++;
                    $packageSize += filesize($file);
                }
            }
            
            if ($packageFiles > 0) {
                $packages[] = [
                    'bundle_id' => $bundleId,
                    'file_count' => $packageFiles,
                    'size_kb' => round($packageSize / 1024, 2),
                    'latest_date' => $latestDate,
                ];
                
                $totalFiles += $packageFiles;
                $totalSize += $packageSize;
            }
        }
        
        // Sort packages by size descending
        usort($packages, function($a, $b) {
            return $b['size_kb'] <=> $a['size_kb'];
        });
        
        return [
            'total_files' => $totalFiles,
            'total_size_kb' => round($totalSize / 1024, 2),
            'packages' => $packages,
        ];
    }

    /**
     * Scan processed directory
     */
    private function scanProcessedDirectory(string $basePath): array
    {
        $totalFiles = 0;
        $totalSize = 0;
        $files = [];
        
        if (!is_dir($basePath)) {
            return [
                'total_files' => 0,
                'total_size_kb' => 0,
                'files' => [],
            ];
        }
        
        // Get all files in processed directory
        $filePaths = array_filter(glob($basePath . '/*'), 'is_file');
        
        foreach ($filePaths as $filePath) {
            $filename = basename($filePath);
            $fileSize = filesize($filePath);
            $modifiedTime = filemtime($filePath);
            
            $files[] = [
                'filename' => $filename,
                'size_kb' => round($fileSize / 1024, 2),
                'modified_time' => $modifiedTime,
            ];
            
            $totalFiles++;
            $totalSize += $fileSize;
        }
        
        // Sort files by modification time descending (newest first)
        usort($files, function($a, $b) {
            return $b['modified_time'] <=> $a['modified_time'];
        });
        
        return [
            'total_files' => $totalFiles,
            'total_size_kb' => round($totalSize / 1024, 2),
            'files' => $files,
        ];
    }

    /**
     * Download SQL dump of the database (only users, packages, scopes, releases, release_artifacts)
     */
    public function downloadDump()
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        
        // Only dump these specific tables (matching nuke functionality)
        $tablesToDump = ['users', 'packages', 'scopes', 'releases', 'release_artifacts'];
        
        $dumpContent = '';
        $filename = 'database_dump_' . date('Y-m-d_His') . '.sql';
        
        if ($connection === 'sqlite') {
            // For SQLite, read the database file
            $dbPath = $config['database'];
            if (File::exists($dbPath)) {
                $dumpContent = "-- SQLite Database Dump\n";
                $dumpContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
                $dumpContent .= "-- Tables: " . implode(', ', $tablesToDump) . "\n\n";
                
                foreach ($tablesToDump as $tableName) {
                    // Check if table exists
                    $tableExists = DB::selectOne("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$tableName]);
                    if (!$tableExists) {
                        continue; // Skip if table doesn't exist
                    }
                    
                    $dumpContent .= "\n-- Table: {$tableName}\n";
                    $dumpContent .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                    
                    // Get CREATE TABLE statement
                    $createTable = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$tableName]);
                    if ($createTable) {
                        $dumpContent .= $createTable->sql . ";\n\n";
                    }
                    
                    // Get table data
                    $rows = DB::table($tableName)->get();
                    if ($rows->count() > 0) {
                        $dumpContent .= "-- Data for table {$tableName}\n";
                        foreach ($rows as $row) {
                            $columns = array_keys((array)$row);
                            $values = array_map(function($value) {
                                if ($value === null) return 'NULL';
                                if (is_numeric($value)) return $value;
                                return "'" . addslashes($value) . "'";
                            }, array_values((array)$row));
                            
                            $dumpContent .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                        }
                        $dumpContent .= "\n";
                    }
                }
            } else {
                return redirect()->back()->withErrors(['error' => 'Database file not found.']);
            }
        } else {
            // For MySQL/MariaDB, use mysqldump if available
            $host = $config['host'];
            $port = $config['port'] ?? 3306;
            $database = $config['database'];
            $username = $config['username'];
            $password = $config['password'];
            
            // Build mysqldump command with specific tables
            $tablesList = implode(' ', array_map('escapeshellarg', $tablesToDump));
            $command = sprintf(
                'mysqldump -h %s -P %s -u %s %s %s %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                $password ? '-p' . escapeshellarg($password) : '',
                escapeshellarg($database),
                $tablesList
            );
            
            $dumpContent = shell_exec($command);
            
            if ($dumpContent === null || strpos($dumpContent, 'mysqldump:') === 0) {
                // Fallback: generate dump manually using Laravel
                $dumpContent = "-- MySQL Database Dump\n";
                $dumpContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
                $dumpContent .= "-- Tables: " . implode(', ', $tablesToDump) . "\n\n";
                $dumpContent .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
                $dumpContent .= "START TRANSACTION;\n";
                $dumpContent .= "SET time_zone = \"+00:00\";\n\n";
                
                foreach ($tablesToDump as $tableName) {
                    // Check if table exists
                    $tableExists = DB::selectOne("SHOW TABLES LIKE ?", [$tableName]);
                    if (!$tableExists) {
                        continue; // Skip if table doesn't exist
                    }
                    
                    $dumpContent .= "\n-- Table: {$tableName}\n";
                    $dumpContent .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                    
                    $createTable = DB::selectOne("SHOW CREATE TABLE `{$tableName}`");
                    if ($createTable) {
                        $createKey = 'Create Table';
                        $dumpContent .= $createTable->$createKey . ";\n\n";
                    }
                    
                    $rows = DB::table($tableName)->get();
                    if ($rows->count() > 0) {
                        $dumpContent .= "-- Data for table {$tableName}\n";
                        foreach ($rows as $row) {
                            $columns = array_keys((array)$row);
                            $values = array_map(function($value) {
                                if ($value === null) return 'NULL';
                                if (is_numeric($value)) return $value;
                                return "'" . addslashes($value) . "'";
                            }, array_values((array)$row));
                            
                            $dumpContent .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                        }
                        $dumpContent .= "\n";
                    }
                }
                
                $dumpContent .= "COMMIT;\n";
            }
        }
        
        return response($dumpContent)
            ->header('Content-Type', 'application/sql')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Restore SQL dump from uploaded file (only users, packages, scopes, releases, release_artifacts)
     */
    public function restoreDump(Request $request)
    {
        $request->validate([
            'sql_dump' => 'required|file|mimes:sql,txt|max:10240', // Max 10MB
        ]);
        
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        
        // Only allow operations on these tables (matching nuke functionality)
        $allowedTables = ['users', 'packages', 'scopes', 'releases', 'release_artifacts'];
        
        try {
            $file = $request->file('sql_dump');
            $sqlContent = file_get_contents($file->getRealPath());
            
            // Safety check: verify the dump only contains allowed tables
            foreach ($allowedTables as $table) {
                if (stripos($sqlContent, "`{$table}`") === false && stripos($sqlContent, "{$table}") === false) {
                    // Table not found, but that's okay - might be an empty dump
                }
            }
            
            // Check for potentially dangerous operations on system tables
            $systemTables = ['sessions', 'cache', 'jobs', 'migrations', 'failed_jobs', 'password_reset_tokens'];
            $dangerousOperations = [
                'DROP TABLE' => '/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?[`"]?(\w+)[`"]?/i',
                'INSERT INTO' => '/INSERT\s+INTO\s+[`"]?(\w+)[`"]?/i',
                'DELETE FROM' => '/DELETE\s+FROM\s+[`"]?(\w+)[`"]?/i',
                'UPDATE' => '/UPDATE\s+[`"]?(\w+)[`"]?/i',
                'CREATE TABLE' => '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(\w+)[`"]?/i',
            ];
            
            foreach ($dangerousOperations as $operation => $pattern) {
                if (preg_match_all($pattern, $sqlContent, $matches)) {
                    foreach ($matches[1] as $tableName) {
                        $tableName = strtolower(trim($tableName, '`"'));
                        // Check if it's a system table or not in allowed tables
                        if (in_array($tableName, $systemTables) || !in_array($tableName, $allowedTables)) {
                            return redirect()->back()
                                ->withErrors(['error' => "SQL dump contains {$operation} operation on table '{$tableName}'. Only operations on users, packages, scopes, releases, and release_artifacts are allowed."]);
                        }
                    }
                }
            }
            
            if ($connection === 'sqlite') {
                // For SQLite, we need to execute the SQL statements
                // Split by semicolons and execute each statement
                $rejectedStatement = null;
                $statements = array_filter(
                    array_map('trim', explode(';', $sqlContent)),
                    function($stmt) use ($allowedTables, &$rejectedStatement) {
                        if (empty($stmt) || preg_match('/^--/', $stmt) || preg_match('/^\/\*/', $stmt)) {
                            return false;
                        }
                        
                        // Allow SET, START, COMMIT, and other transaction statements
                        if (preg_match('/^(SET|START|COMMIT|ROLLBACK)/i', trim($stmt))) {
                            return true;
                        }
                        
                        // Check for table operations and extract table name
                        $tablePatterns = [
                            '/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?[`"]?(\w+)[`"]?/i',
                            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(\w+)[`"]?/i',
                            '/INSERT\s+INTO\s+[`"]?(\w+)[`"]?/i',
                            '/UPDATE\s+[`"]?(\w+)[`"]?/i',
                            '/DELETE\s+FROM\s+[`"]?(\w+)[`"]?/i',
                        ];
                        
                        foreach ($tablePatterns as $pattern) {
                            if (preg_match($pattern, $stmt, $matches)) {
                                $tableName = strtolower(trim($matches[1], '`"'));
                                if (in_array($tableName, $allowedTables)) {
                                    return true;
                                } else {
                                    $rejectedStatement = $stmt;
                                    return false;
                                }
                            }
                        }
                        
                        // If no table operation found, check if it references allowed tables
                        foreach ($allowedTables as $table) {
                            if (stripos($stmt, "`{$table}`") !== false || stripos($stmt, "{$table}") !== false) {
                                return true;
                            }
                        }
                        
                        // If statement doesn't match any pattern, reject it
                        $rejectedStatement = $stmt;
                        return false;
                    }
                );
                
                if (isset($rejectedStatement) && $rejectedStatement) {
                    // Try to extract table name from rejected statement
                    $tableName = 'unknown';
                    foreach (['DROP TABLE', 'CREATE TABLE', 'INSERT INTO', 'UPDATE', 'DELETE FROM'] as $op) {
                        if (stripos($rejectedStatement, $op) !== false) {
                            if (preg_match('/[`"]?(\w+)[`"]?/i', $rejectedStatement, $matches)) {
                                $tableName = $matches[1];
                            }
                        }
                    }
                    return redirect()->back()
                        ->withErrors(['error' => "SQL dump contains operation on table '{$tableName}' which is not allowed. Only operations on users, packages, scopes, releases, and release_artifacts are allowed."]);
                }
                
                DB::beginTransaction();
                try {
                    foreach ($statements as $statement) {
                        if (!empty(trim($statement))) {
                            DB::statement($statement);
                        }
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            } else {
                // For MySQL/MariaDB
                // Remove comments and split statements
                $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
                $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);
                
                $rejectedStatement = null;
                $statements = array_filter(
                    array_map('trim', explode(';', $sqlContent)),
                    function($stmt) use ($allowedTables, &$rejectedStatement) {
                        if (empty($stmt)) {
                            return false;
                        }
                        
                        // Allow SET, START, COMMIT, and other transaction statements
                        if (preg_match('/^(SET|START|COMMIT|ROLLBACK)/i', trim($stmt))) {
                            return true;
                        }
                        
                        // Check for table operations and extract table name
                        $tablePatterns = [
                            '/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?[`"]?(\w+)[`"]?/i',
                            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(\w+)[`"]?/i',
                            '/INSERT\s+INTO\s+[`"]?(\w+)[`"]?/i',
                            '/UPDATE\s+[`"]?(\w+)[`"]?/i',
                            '/DELETE\s+FROM\s+[`"]?(\w+)[`"]?/i',
                        ];
                        
                        foreach ($tablePatterns as $pattern) {
                            if (preg_match($pattern, $stmt, $matches)) {
                                $tableName = strtolower(trim($matches[1], '`"'));
                                if (in_array($tableName, $allowedTables)) {
                                    return true;
                                } else {
                                    $rejectedStatement = $stmt;
                                    return false;
                                }
                            }
                        }
                        
                        // If no table operation found, check if it references allowed tables
                        foreach ($allowedTables as $table) {
                            if (stripos($stmt, "`{$table}`") !== false || stripos($stmt, "{$table}") !== false) {
                                return true;
                            }
                        }
                        
                        // If statement doesn't match any pattern, reject it
                        $rejectedStatement = $stmt;
                        return false;
                    }
                );
                
                if ($rejectedStatement !== null) {
                    // Try to extract table name from rejected statement
                    $tableName = 'unknown';
                    $operation = 'operation';
                    foreach (['DROP TABLE' => 'DROP TABLE', 'CREATE TABLE' => 'CREATE TABLE', 'INSERT INTO' => 'INSERT INTO', 'UPDATE' => 'UPDATE', 'DELETE FROM' => 'DELETE FROM'] as $op => $opName) {
                        if (stripos($rejectedStatement, $op) !== false) {
                            $operation = $opName;
                            if (preg_match('/[`"]?(\w+)[`"]?/i', $rejectedStatement, $matches)) {
                                $tableName = $matches[1];
                            }
                            break;
                        }
                    }
                    return redirect()->back()
                        ->withErrors(['error' => "SQL dump contains {$operation} operation on table '{$tableName}' which is not allowed. Only operations on users, packages, scopes, releases, and release_artifacts are allowed."]);
                }
                
                DB::beginTransaction();
                try {
                    foreach ($statements as $statement) {
                        if (!empty(trim($statement))) {
                            DB::statement($statement);
                        }
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }
            
            return redirect()->route('admin.experiments')
                ->with('success', 'Database successfully restored from backup.');
                
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to restore database: ' . $e->getMessage()]);
        }
    }

    /**
     * Clear all users, packages, scopes, releases, and release artifacts (truncate tables)
     */
    public function clearData()
    {
        try {
            DB::beginTransaction();
            
            // Disable foreign key checks temporarily (for MySQL/MariaDB)
            if (config('database.default') !== 'sqlite') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }
            
            // Delete in order to respect foreign key constraints
            // 1. Release artifacts (depends on releases)
            DB::table('release_artifacts')->truncate();
            
            // 2. Releases (depends on packages)
            DB::table('releases')->truncate();
            
            // 3. Packages (depends on scopes)
            DB::table('packages')->truncate();
            
            // 4. Scopes
            DB::table('scopes')->truncate();
            
            // 5. Users
            DB::table('users')->truncate();
            
            // Re-enable foreign key checks
            if (config('database.default') !== 'sqlite') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            
            DB::commit();
            
            return redirect()->route('admin.experiments')
                ->with('success', 'All users, packages, scopes, releases, and release artifacts have been deleted.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Re-enable foreign key checks in case of error
            if (config('database.default') !== 'sqlite') {
                try {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                } catch (\Exception $fkException) {
                    // Ignore if already enabled
                }
            }
            
            return redirect()->back()
                ->withErrors(['error' => 'Failed to delete data: ' . $e->getMessage()]);
        }
    }

    /**
     * NUKE: Drop all tables from the database
     */
    public function nukeData()
    {
        try {
            DB::beginTransaction();
            
            $connection = config('database.default');
            
            // Disable foreign key checks temporarily (for MySQL/MariaDB)
            if ($connection !== 'sqlite') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }
            
            // Get all table names
            if ($connection === 'sqlite') {
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                foreach ($tables as $table) {
                    DB::statement('DROP TABLE IF EXISTS `' . $table->name . '`');
                }
            } else {
                // MySQL/MariaDB
                $tables = DB::select('SHOW TABLES');
                $databaseName = config("database.connections.{$connection}.database");
                $key = 'Tables_in_' . $databaseName;
                
                foreach ($tables as $table) {
                    $tableName = $table->$key;
                    DB::statement('DROP TABLE IF EXISTS `' . $tableName . '`');
                }
            }
            
            // Re-enable foreign key checks
            if ($connection !== 'sqlite') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            
            DB::commit();
            
            return redirect()->route('admin.experiments')
                ->with('success', 'All tables have been dropped from the database.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Re-enable foreign key checks in case of error
            if (config('database.default') !== 'sqlite') {
                try {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                } catch (\Exception $fkException) {
                    // Ignore if already enabled
                }
            }
            
            return redirect()->back()
                ->withErrors(['error' => 'Failed to drop tables: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete an incoming package directory (bundle_id folder)
     */
    public function deleteIncomingPackage(Request $request)
    {
        $request->validate([
            'bundle_id' => 'required|string',
        ]);
        
        $bundleId = $request->input('bundle_id');
        $bundlePath = storage_path('app/private/incoming/' . $bundleId);
        
        if (!is_dir($bundlePath)) {
            return redirect()->back()
                ->withErrors(['error' => 'Package directory not found.']);
        }
        
        try {
            File::deleteDirectory($bundlePath);
            return redirect()->route('admin.experiments')
                ->with('success', "Package directory '{$bundleId}' deleted successfully.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to delete package directory: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete all incoming directories except the latest date folder for each package
     */
    public function deleteAllIncomingButLatest()
    {
        $incomingPath = storage_path('app/private/incoming');
        
        if (!is_dir($incomingPath)) {
            return redirect()->back()
                ->withErrors(['error' => 'Incoming directory not found.']);
        }
        
        try {
            $bundleDirs = array_filter(glob($incomingPath . '/*'), 'is_dir');
            $deletedCount = 0;
            
            foreach ($bundleDirs as $bundleDir) {
                $dateDirs = array_filter(glob($bundleDir . '/*'), 'is_dir');
                
                if (count($dateDirs) <= 1) {
                    continue; // Skip if only one or no date folders
                }
                
                // Find the latest date folder
                $latestDir = null;
                $latestTimestamp = 0;
                
                foreach ($dateDirs as $dateDir) {
                    $timestamp = filemtime($dateDir);
                    if ($timestamp > $latestTimestamp) {
                        $latestTimestamp = $timestamp;
                        $latestDir = $dateDir;
                    }
                }
                
                // Delete all date folders except the latest
                foreach ($dateDirs as $dateDir) {
                    if ($dateDir !== $latestDir) {
                        File::deleteDirectory($dateDir);
                        $deletedCount++;
                    }
                }
            }
            
            return redirect()->route('admin.experiments')
                ->with('success', "Deleted {$deletedCount} old date folders, keeping only the latest for each package.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to delete old folders: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete a processed file
     */
    public function deleteProcessedFile(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
        ]);
        
        $filename = $request->input('filename');
        $filePath = storage_path('app/private/incoming_processed/' . $filename);
        
        if (!file_exists($filePath)) {
            return redirect()->back()
                ->withErrors(['error' => 'File not found.']);
        }
        
        try {
            unlink($filePath);
            return redirect()->route('admin.experiments')
                ->with('success', "File '{$filename}' deleted successfully.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to delete file: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete all processed files except the latest one
     */
    public function deleteAllProcessedButLatest()
    {
        $processedPath = storage_path('app/private/incoming_processed');
        
        if (!is_dir($processedPath)) {
            return redirect()->back()
                ->withErrors(['error' => 'Processed directory not found.']);
        }
        
        try {
            $filePaths = array_filter(glob($processedPath . '/*'), 'is_file');
            
            if (count($filePaths) <= 1) {
                return redirect()->back()
                    ->with('success', 'No files to delete (only one or no files exist).');
            }
            
            // Find the latest file
            $latestFile = null;
            $latestTimestamp = 0;
            
            foreach ($filePaths as $filePath) {
                $timestamp = filemtime($filePath);
                if ($timestamp > $latestTimestamp) {
                    $latestTimestamp = $timestamp;
                    $latestFile = $filePath;
                }
            }
            
            // Delete all files except the latest
            $deletedCount = 0;
            foreach ($filePaths as $filePath) {
                if ($filePath !== $latestFile) {
                    unlink($filePath);
                    $deletedCount++;
                }
            }
            
            return redirect()->route('admin.experiments')
                ->with('success', "Deleted {$deletedCount} old files, keeping only the latest.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to delete files: ' . $e->getMessage()]);
        }
    }

    /**
     * Create example data (scopes, packages, releases)
     */
    public function createExampleData(Request $request)
    {
        $request->validate([
            'num_categories' => 'required|integer|min:1|max:50',
            'num_packages' => 'required|integer|min:1|max:200',
            'num_releases' => 'required|integer|min:1|max:5',
            'base_scope' => 'required|string|regex:/^[a-zA-Z0-9.]+$/',
        ]);

        try {
            $seeder = new ExampleDataSeederService();
            $results = $seeder->seed(
                $request->input('num_categories'),
                $request->input('num_packages'),
                $request->input('num_releases'),
                $request->input('base_scope', config('app.default_scope'))
            );

            $message = sprintf(
                'Example data created successfully: %d user(s), %d scope(s), %d package(s), %d release(s)',
                $results['users_created'],
                $results['scopes_created'],
                $results['packages_created'],
                $results['releases_created']
            );

            return redirect()->route('admin.experiments')
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to create example data: ' . $e->getMessage()])
                ->withInput();
        }
    }

  
}
