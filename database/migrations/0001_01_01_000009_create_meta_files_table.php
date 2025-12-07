<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('meta_files')) {
            Schema::create('meta_files', function (Blueprint $table) {
                $table->id();
                $table->foreignId('package_id')->constrained('packages')->onDelete('cascade');
                $table->string('relative_path', 500)->comment('File or folder path relative to package root');
                $table->string('guid', 32)->comment('Unity GUID (32 hex characters)');
                $table->timestamps();
                
                // Unique constraint: same package + path = same guid
                $table->unique(['package_id', 'relative_path']);
                
                // Index for faster lookups
                $table->index('package_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_files');
    }
};
