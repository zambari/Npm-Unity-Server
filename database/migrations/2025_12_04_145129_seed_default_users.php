<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Optional - adds two users
     */
   public function up(): void
     {
        // Insert default scope
        DB::table('scopes')->insert([
            'scope' => 'com.example',
            'display_name' => 'Example Package Collection',
        ]);

        // Insert default read-only guest user
        $salt = env('PASSWORD_SALT', 'default-salt-change-in-env');
        $password = Hash::make('guest' . $salt);
        
        DB::table('users')->insert([
            'name' => 'Curious Guest',
            'email' => 'guest@guest.com',
            'email_verified_at' => null,
            'password' => $password,
            'disabled' => 0,
            'privileges' => 'NONE',
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // public function up(): void
    // {
    //     DB::table('users')->insert([
    //         [
    //             'id' => 1,
    //             'name' => 'Supervisor',
    //             'email' => 'admin@admin.com',
    //             'email_verified_at' => null,
    //             'password' => '$2y$12$xxaHHiUEM8vI5l7izWGmQ.JIv/LcQ2NZl2vf8G1XWhfL1y/VIjfBy',
    //             'disabled' => 0,
    //             'remember_token' => null,
    //             'created_at' => '2025-12-04 13:49:12',
    //             'updated_at' => '2025-12-04 14:26:44',
    //         ],
    //         [
    //             'id' => 2,
    //             'name' => 'Test',
    //             'email' => 'test@test.com',
    //             'email_verified_at' => null,
    //             'password' => '$2y$12$xJeWbya36PDoVHZXZcLItuLnp9LXI6edCPGGtcfypS2GpDHJakxh6',
    //             'disabled' => 0,
    //             'remember_token' => null,
    //             'created_at' => '2025-12-04 13:49:31',
    //             'updated_at' => '2025-12-04 14:26:35',
    //         ],
    //     ]);
    // }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
