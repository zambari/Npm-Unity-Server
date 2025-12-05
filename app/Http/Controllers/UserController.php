<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();
        return view('admin.users.index', compact('users'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:5',
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = $request->password; // Will be hashed via setPasswordAttribute
        $user->disabled = false;
        $user->save();

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }

    /**
     * Toggle user disabled status
     */
    public function toggleDisabled(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->disabled = !$user->disabled;
        $user->save();

        $status = $user->disabled ? 'disabled' : 'enabled';
        return redirect()->route('admin.users')->with('success', "User has been {$status}.");
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, $id)
    {
        $request->validate([
            'new_password' => 'required|string|min:5',
        ]);

        $user = User::findOrFail($id);
        $user->password = $request->new_password; // Will be hashed via setPasswordAttribute
        $user->save();

        return redirect()->route('admin.users')->with('success', 'Password has been reset successfully.');
    }
}

