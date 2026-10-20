<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $req)
    {
        $v = $req->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed',
        ]);

        $user = User::create($v);

        return response()->json([
            'success' => true,
            'messages' => 'Account registered successfully',
            'data' => $user,
        ]);
    }

    public function login(Request $req)
    {
        $v = $req->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $v['email'])->first();

        if (! $user || ! Hash::check($v['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'messages' => 'The provided credentials are incorrect',
                'errors' => [],
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'messages' => 'Login successful',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ],
        ]);
    }

    public function logout(Request $req)
    {

        $req->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'messages' => 'Logout successful',
            'data' => null,
        ]);
    }
}
