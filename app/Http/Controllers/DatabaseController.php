<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseController extends Controller
{
    /**
     * Initialize the database by running migrations if they haven't been run yet.
     * This endpoint is safe to call multiple times - it only runs migrations if
     * the migrations table doesn't exist.
     */
    public function initialize()
    {
        try {
            // Test database connection first
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
                // Check if any migrations have been run
                $migrationCount = DB::table('migrations')->count();
                
                if ($migrationCount > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Database is already initialized. Migrations have already been run.',
                        'migrations_run' => $migrationCount
                    ], 200);
                }
            }
            
            // Run migrations
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Database initialized successfully. All migrations have been run.',
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
}

