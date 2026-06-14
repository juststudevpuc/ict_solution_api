<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    // 1. Get all staff
    public function index()
    {
        $staff = User::where('role', 'staff')->orderBy('created_at', 'desc')->get();
        // $staff = User::all();
        return response()->json($staff);
    }

    // 2. Create a new staff member
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string'
        ]);

        $staff = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'staff', // This locks the user as a staff member
            'phone' => $request->phone,
        ]);

        return response()->json(['message' => 'Staff created successfully', 'data' => $staff]);
    }

    // 3. Update an existing staff member
    public function update(Request $request, string $id)
    {
        $staff = User::where('role', 'staff')->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string'
        ]);

        // Only hash and update the password if the admin typed a new one
        if ($request->filled('password')) {
            $staff->password = Hash::make($request->password);
        }

        $staff->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        return response()->json(['message' => 'Staff updated successfully', 'data' => $staff]);
    }

    // 4. Delete a staff member
    public function destroy(string $id)
    {
        $staff = User::where('role', 'staff')->findOrFail($id);
        $staff->delete();

        return response()->json(['message' => 'Staff deleted successfully']);
    }
}
