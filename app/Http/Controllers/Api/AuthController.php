<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Handle an authentication attempt.
     */
    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return response()->json([
                'message' => 'Login successful',
                'user' => Auth::user(),
            ]);
        }

        return response()->json([
            'message' => 'Login failed',
        ], 401);
    }

    public function register(Request $request)
    {
        //validate
        $data = $request->validate([
            'name' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed'],
        ]);

        //Hash the password
        $data['password'] = bcrypt($data['password']);

        //Create the user
        $user = \App\Models\User::create($data);

        // 3. (Optional) Automatically log in the user
        // Auth::login($user);
        // $request->session()->regenerate();

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
        ], 201);
    }
}
