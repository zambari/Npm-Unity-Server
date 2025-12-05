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
        if (!session('admin_authenticated') || !session('super_user')) {
            return redirect()->route('welcome')->withErrors(['access' => 'Access denied. Super-user privileges required.']);
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

