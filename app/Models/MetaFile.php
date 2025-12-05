<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaFile extends Model
{
    protected $fillable = [
        'package_id',
        'relative_path',
        'guid',
    ];

    /**
     * Get the package this meta file belongs to.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Generate a Unity-style GUID (32 hex characters, lowercase)
     */
    public static function generateGuid(): string
    {
        return strtolower(bin2hex(random_bytes(16)));
    }
}
