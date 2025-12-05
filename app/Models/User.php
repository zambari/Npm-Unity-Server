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
}

