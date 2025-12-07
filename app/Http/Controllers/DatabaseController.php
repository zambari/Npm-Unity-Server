<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class DatabaseController extends Controller
{
    /**
     * Initialize the database by running migrations if they haven't been run yet.
     * This endpoint is safe to call multiple times - it will run any pending migrations
     * that haven't been executed yet, even if some migrations have already been run.
     */
    public function initialize()
    {
        try {
            // For SQLite, ensure the database file exists BEFORE trying to connect
            $driver = config('database.default');
            
            if ($driver === 'sqlite') {
                $config = config('database.connections.sqlite');
                $databasePath = $config['database'] ?? database_path('database.sqlite');
                
                // Convert relative paths to absolute
                $isAbsolute = str_starts_with($databasePath, '/') || 
                              preg_match('/^[A-Za-z]:[\\\\\/]/', $databasePath);
                
                if (!$isAbsolute) {
                    $databasePath = database_path($databasePath);
                }
                
                // Ensure the directory exists
                $databaseDir = dirname($databasePath);
                if (!File::exists($databaseDir)) {
                    File::makeDirectory($databaseDir, 0755, true);
                }
                
                // Create empty database file if it doesn't exist
                if (!File::exists($databasePath)) {
                    File::put($databasePath, '');
                }
            }
            
            // Now test database connection (file should exist for SQLite)
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection failed. Please check your database configuration in .env file.',
                'error' => $e->getMessage(),
                'hint' => 'Verify DB_CONNECTION, DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD are correct.'
            ], 500);
        }

        try {
            // Check if migrations table exists
            $migrationsTableExists = Schema::hasTable('migrations');
            
            if ($migrationsTableExists) {
                // Get the list of migrations that have been run
                $runMigrations = DB::table('migrations')->pluck('migration')->toArray();
                
                // Get all available migration files
                $migrationFiles = glob(database_path('migrations/*.php'));
                $availableMigrations = [];
                foreach ($migrationFiles as $file) {
                    $filename = basename($file, '.php');
                    $availableMigrations[] = $filename;
                }
                
                // Check if there are pending migrations
                $pendingMigrations = array_diff($availableMigrations, $runMigrations);
                
                if (empty($pendingMigrations)) {
                    // All migrations have been run
                    return response()->json([
                        'success' => false,
                        'message' => 'Database is already initialized. All migrations have been run.',
                        'migrations_run' => count($runMigrations),
                        'total_migrations' => count($availableMigrations)
                    ], 200);
                }
                
                // There are pending migrations, run them
                Artisan::call('migrate', ['--force' => true]);
                $output = Artisan::output();
                
                // Get updated count after running migrations
                $updatedRunMigrations = DB::table('migrations')->pluck('migration')->toArray();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Database migrations completed. Pending migrations have been run.',
                    'migrations_run_before' => count($runMigrations),
                    'migrations_run_after' => count($updatedRunMigrations),
                    'pending_migrations_run' => count($pendingMigrations),
                    'output' => trim($output)
                ], 200);
            }
            
            // Migrations table doesn't exist, run all migrations
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            
            $migrationCount = DB::table('migrations')->count();
            
            return response()->json([
                'success' => true,
                'message' => 'Database initialized successfully. All migrations have been run.',
                'migrations_run' => $migrationCount,
                'output' => trim($output)
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize database: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset the database by dropping all tables and data.
     * This works for all database types (SQLite, MySQL, PostgreSQL, etc.).
     * WARNING: This will permanently delete all data!
     */
    public function reset()
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            $config = config("database.connections.{$driver}");

            $droppedTables = [];

            if ($driver === 'sqlite') {
                // For SQLite, get all tables from sqlite_master
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                
                // Disable foreign key checks for SQLite
                DB::statement('PRAGMA foreign_keys = OFF');
                
                foreach ($tables as $table) {
                    $tableName = $table->name;
                    DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
                    $droppedTables[] = $tableName;
                }
                
                // Re-enable foreign key checks
                DB::statement('PRAGMA foreign_keys = ON');
                
            } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                // For MySQL/MariaDB, drop all tables
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                
                $tables = DB::select('SHOW TABLES');
                $tableKey = 'Tables_in_' . $config['database'];
                
                foreach ($tables as $table) {
                    $tableName = $table->$tableKey;
                    DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
                    $droppedTables[] = $tableName;
                }
                
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                
            } elseif ($driver === 'pgsql') {
                // For PostgreSQL, drop all tables
                DB::statement('SET session_replication_role = replica;');
                
                $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
                
                foreach ($tables as $table) {
                    $tableName = $table->tablename;
                    DB::statement("DROP TABLE IF EXISTS \"{$tableName}\" CASCADE");
                    $droppedTables[] = $tableName;
                }
                
                DB::statement('SET session_replication_role = DEFAULT;');
                
            } else {
                // Generic fallback: try to get tables using information_schema
                try {
                    $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()");
                    foreach ($tables as $table) {
                        $tableName = $table->table_name;
                        DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
                        $droppedTables[] = $tableName;
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unsupported database driver or unable to drop tables: ' . $e->getMessage(),
                        'driver' => $driver,
                        'error' => $e->getMessage()
                    ], 500);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'All database tables dropped successfully. You can now run /initializedb to recreate the tables.',
                'dropped_tables' => $droppedTables,
                'tables_count' => count($droppedTables),
                'driver' => $driver
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset database: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
}

