<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scope extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'scope',
        'display_name',
    ];

    /**
     * Get all packages for this scope.
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'scope_id');
    }
}

