<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * Check if the current user is read-only
     * Returns true if user is read-only, false otherwise
     * Super-users (ENV credentials) always have write access
     */
    protected function isReadOnlyUser(): bool
    {
        // Super-users (ENV credentials) always have write access
        if (session('super_user')) {
            return false;
        }
        
        // Check if authenticated user (database user) is read-only
        if (Auth::check() && Auth::user()) {
            return Auth::user()->readOnlyUser();
        }
        
        return false;
    }
    
    /**
     * Abort with error message if user is read-only
     */
    protected function checkReadOnly()
    {
        if ($this->isReadOnlyUser()) {
            abort(403, 'Sorry, this user is not allowed to make changes.');
        }
    }
}
