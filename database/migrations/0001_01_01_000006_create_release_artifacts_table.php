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
        if (!Schema::hasTable('release_artifacts')) {
            Schema::create('release_artifacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('release_id')->constrained('releases')->cascadeOnDelete();
                $table->string('url', 255)->nullable();
                $table->integer('status');
                $table->string('upload_name', 255)->nullable();
                $table->string('url_meta', 255)->nullable()->comment('Additional metadata about the URL');
                $table->string('shasum', 40)->nullable();
                $table->timestamp('upload_date')->nullable();
                $table->timestamps();
               
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('release_artifacts');
    }
};

