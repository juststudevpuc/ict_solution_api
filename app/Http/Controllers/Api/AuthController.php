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
        $user = User::paginate(10);

        return response()->json($user, 200);
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

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        // 1. Validate the incoming data
        $request->validate([
            "name" => "required|string|max:255|min:3",
            "phone" => "nullable|string|max:20",
            "address" => "nullable|string",
            // Password validation: Only require currentPassword IF they type a newPassword
            "currentPassword" => "required_with:newPassword",
            "newPassword" => "nullable|string|min:3",
            "image" => "nullable|image|mimes:jpeg,png,jpg,gif|max:2048"
        ]);
        // --- NEW: Handle Image Upload ---
        if ($request->hasFile('image')) {
            // Save the file into storage/app/public/avatars
            $path = $request->file('image')->store('avatars', 'public');
            // Save the URL to the database
            $user->image = '/storage/' . $path;
        }

        // 2. Check and Update Password (if they provided one)
        if ($request->filled("newPassword")) {
            // Check if the current password they typed matches the database
            if (!Hash::check($request->currentPassword, $user->password)) {
                return response()->json([
                    "error" => true,
                    "message" => "Your current password is incorrect."
                ], 400);
            }
            // Hash and set the new password
            $user->password = Hash::make($request->newPassword);
        }

        // 3. Update basic information
        $user->name = $request->name;
        if ($request->filled("phone")) $user->phone = $request->phone;
        if ($request->filled("address")) $user->address = $request->address;

        $user->save();

        return response()->json([
            "user" => $user,
            "message" => "Profile updated successfully!"
        ], 200);
    }
}
