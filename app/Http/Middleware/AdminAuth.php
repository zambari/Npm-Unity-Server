<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if already authenticated via session
        if (session('admin_authenticated')) {
            return $next($request);
        }
        
        // Check for remember token cookie (for super users)
        $rememberToken = $request->cookie('admin_remember_token');
        if ($rememberToken) {
            // Validate the remember token by regenerating it with current credentials
            $expectedToken = hash('sha256', env('ADMIN_USERNAME') . env('ADMIN_PASSWORD') . env('APP_KEY', 'default-key'));
            if (hash_equals($expectedToken, $rememberToken)) {
                // Token is valid, restore session
                Session::put('admin_authenticated', true);
                Session::put('super_user', true);
                Session::put('admin_email', env('ADMIN_EMAIL'));
                Session::put('remember_token', $rememberToken);
                return $next($request);
            }
        }
        
        return redirect('/loginform');
    }
}
