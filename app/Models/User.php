<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'disabled',
        'privileges',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'disabled' => 'boolean',
        ];
    }

    /**
     * Hash password with salt from env before saving
     */
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $salt = env('PASSWORD_SALT', 'default-salt-change-in-env');
            $this->attributes['password'] = Hash::make($value . $salt);
        }
    }

    /**
     * Verify password with salt from env
     */
    public function verifyPassword($password)
    {
        $salt = env('PASSWORD_SALT', 'default-salt-change-in-env');
        return Hash::check($password . $salt, $this->password);
    }

    /**
     * Check if user is read-only (privileges contains the read-only token)
     * If privileges is null or empty, user has edit access (default)
     */
    public function readOnlyUser(): bool
    {
        if (empty($this->privileges)) {
            return false; // No privileges set means edit is enabled
        }
        $readOnlyToken = config('app.read_only_privilege_token', 'NONE');
        return str_contains($this->privileges, $readOnlyToken);
    }
}

