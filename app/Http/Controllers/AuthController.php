<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{


    public function showLogin()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        // Check ENV credentials first (super-user)
        if ($request->username === env('ADMIN_USERNAME') && 
            $request->password === env('ADMIN_PASSWORD')) {
            
            // Check if admin access is allowed
            $adminAccessAllowed = trim(strtolower(env('ADMIN_ACCESS_ALLOWED', 'true')));
            // Accept 'true', '1', 'yes', 'on' as valid true values
            $isAllowed = in_array($adminAccessAllowed, ['true', '1', 'yes', 'on'], true);
            Log::info('AuthController login - ADMIN_ACCESS_ALLOWED check', [
                'raw_value' => env('ADMIN_ACCESS_ALLOWED'),
                'trimmed_lower' => $adminAccessAllowed,
                'is_allowed' => $isAllowed
            ]);
            
            if (!$isAllowed) {
                return back()->withErrors([
                    'access' => 'Admin access is currently disabled. Please set ADMIN_ACCESS_ALLOWED=true in your .env file to enable super-admin access. (Current value: "' . env('ADMIN_ACCESS_ALLOWED', 'not set') . '")'
                ]);
            }
            
            Session::put('admin_authenticated', true);
            Session::put('super_user', true);
            Session::put('admin_email', env('ADMIN_EMAIL'));
            
            Log::info('User logged in (super-user)', [
                'username' => $request->username,
                'email' => env('ADMIN_EMAIL'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);
            
            return redirect()->route('admin.users');
        }

        // Check database credentials (normal user)
        $user = User::where('email', $request->username)
            ->orWhere('name', $request->username)
            ->first();

        if ($user && !$user->disabled && $user->verifyPassword($request->password)) {
            // Use Laravel's standard authentication so @auth works
            Auth::login($user);
            Session::put('admin_authenticated', true);
            Session::put('super_user', false);
            
            Log::info('User logged in (database user)', [
                'user_id' => $user->id,
                'username' => $user->name,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);
            
            return redirect()->route('welcome');
        }

        Log::warning('Failed login attempt', [
            'username' => $request->username,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);

        return back()->withErrors(['credentials' => 'Invalid credentials']);
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        $isSuperUser = Session::get('super_user', false);
        $email = $isSuperUser ? Session::get('admin_email') : ($user ? $user->email : null);
        $username = $isSuperUser ? env('ADMIN_USERNAME') : ($user ? $user->name : null);
        $userId = $user ? $user->id : null;
        
        Auth::logout();
        Session::forget('admin_authenticated');
        Session::forget('super_user');
        Session::forget('admin_email');
        
        Log::info('User logged out', [
            'user_id' => $userId,
            'username' => $username,
            'email' => $email,
            'is_super_user' => $isSuperUser,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
        
        return redirect()->route('welcome');
    }
}
