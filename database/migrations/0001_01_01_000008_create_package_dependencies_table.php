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
        if (!Schema::hasTable('package_dependencies')) {
            Schema::create('package_dependencies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('release_id')->constrained('releases')->cascadeOnDelete();
                $table->string('bundle_id', 255)->nullable();
                $table->string('version', 45)->nullable();
                $table->foreignId('dependency_release_id')->nullable()->constrained('releases')->nullOnDelete();
                $table->string('external_dependency', 200)->nullable()->comment('For dependencies outside this registry');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_dependencies');
    }
};

