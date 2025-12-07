<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SuperUserAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if already authenticated via session
        if (session('admin_authenticated') && session('super_user')) {
            // Continue with access check below
        } else {
            // Check for remember token cookie (for super users)
            $rememberToken = $request->cookie('admin_remember_token');
            if ($rememberToken) {
                // Validate the remember token by regenerating it with current credentials
                $expectedToken = hash('sha256', env('ADMIN_USERNAME') . env('ADMIN_PASSWORD') . env('APP_KEY', 'default-key'));
                if (hash_equals($expectedToken, $rememberToken)) {
                    // Token is valid, restore session
                    session(['admin_authenticated' => true, 'super_user' => true, 'admin_email' => env('ADMIN_EMAIL'), 'remember_token' => $rememberToken]);
                } else {
                    return redirect()->route('welcome')->withErrors(['access' => 'Access denied. Super-user privileges required.']);
                }
            } else {
                return redirect()->route('welcome')->withErrors(['access' => 'Access denied. Super-user privileges required.']);
            }
        }

        // Check if admin access is allowed via environment variable
        $adminAccessAllowed = trim(strtolower(env('ADMIN_ACCESS_ALLOWED', 'true')));
        // Accept 'true', '1', 'yes', 'on' as valid true values
        $isAllowed = in_array($adminAccessAllowed, ['true', '1', 'yes', 'on'], true);
        Log::info('SuperUserAuth middleware', [
            'ADMIN_ACCESS_ALLOWED_raw' => env('ADMIN_ACCESS_ALLOWED'),
            'ADMIN_ACCESS_ALLOWED_processed' => $adminAccessAllowed,
            'is_allowed' => $isAllowed
        ]);
        
        if (!$isAllowed) {
            return redirect()->route('welcome')->withErrors([
                'access' => 'Admin access is currently disabled. Please set ADMIN_ACCESS_ALLOWED=true in your .env file to enable super-admin access. (Current value: "' . env('ADMIN_ACCESS_ALLOWED', 'not set') . '")'
            ]);
        }

        return $next($request);
    }
}

