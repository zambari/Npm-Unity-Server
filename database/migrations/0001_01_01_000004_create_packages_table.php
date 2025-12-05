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
        if (!Schema::hasTable('packages')) {
            Schema::create('packages', function (Blueprint $table) {
                $table->id();
                $table->string('bundle_id', 45)->unique()->comment('Unique package identifier (e.g., com.example.mypackage)');
                $table->string('product_name', 45)->nullable()->comment('Display/product name');
                $table->string('description', 255)->nullable();
                $table->integer('status')->nullable();
                $table->boolean('disabled')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('scope_id')->nullable()->constrained('scopes')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};

