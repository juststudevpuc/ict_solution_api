<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function index()
    {
        $user = User::all(); // Fetch everyone from the database
        return response()->json([
            "data" => $user,
            "message" => "Users retrieved successfully"
        ], 200);
    }
    public function register(Request $request)
    {
        $request->validate([
            "name" => "required|string|max:255|min:3",
            "email" => "required|email|unique:users,email",
            "password" => "required|string|min:3|confirmed",
            "role" => "required|string|max:255|min:3",
        ]);

        $user = User::create([
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
            "role" => $request->role,
        ]);

        return response()->json([
            "user" => $user,
            "message" => "CREATED USER SUCCESSFULLY"
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            "email" => "required|email",
            "password" => "required|string|min:3"
        ]);

        $user = User::where("email", $request->email)->first();

        if (!$user) {
            return response()->json(["message" => "User not found"], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(["message" => "Incorrect password"], 404);
        }

        $token = $user->createToken("authtoken")->plainTextToken;

        $cookie = cookie(
            "auth_token",
            $token,
            60 * 24 * 7, // 7 days
            "/",
            null,
            true,
            true,
            false,
            "Strict"
        );

        return response()->json([
            "user" => $user,
            "token" => $token,
            "message" => $user->role === 'admin' ? "Admin logged in successfully" : "User logged in successfully"
        ], 200)->withCookie($cookie);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        $cookie = Cookie::forget("auth_token");

        return response()->json([
            "message" => "User logged out successfully"
        ], 200)->withCookie($cookie);
    }

    public function me(Request $request)
    {
        return response()->json([
            "user" => $request->user(),
            "message" => "Get user successfully"
        ], 200);
    }
}
