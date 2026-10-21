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
            'email' => 'required|email',
            'password' => 'required|confirmed',
        ]);

        if (User::where('email', $v['email'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already registered.',
                'errors' => [
                    ['field' => 'email', 'message' => 'Must be unique.'],
                ],
            ], 409);
        }

        $v['role'] = 'customer';
        $user = User::create($v);

        return response()->json([
            'success' => true,
            'message' => 'Account registered successfully.',
            'data' => $user,
        ], 201);
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
                'message' => 'The provided credentials are incorrect.',
                'errors' => [],
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
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
            'message' => 'Logout successful.',
            'data' => null,
        ]);
    }
}
