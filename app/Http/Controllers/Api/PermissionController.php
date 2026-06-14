<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission; // Make sure this is imported!
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId; // Keep this import!

class PermissionController extends Controller
{
    // 1. Send all permissions to React
    public function index()
    {
        $permissions = Permission::all();
        return response()->json($permissions);
    }

    // 2. Receive the updated matrix and save it to MongoDB
    public function updateMatrix(Request $request)
    {
        $matrix = $request->input('matrix');

        foreach ($matrix as $item) {
            if (isset($item['_id'])) {
                // 1. Convert string to MongoDB ObjectId
                $mongoId = new ObjectId($item['_id']);

                // 2. Use the Eloquent Model instead of DB::collection
                Permission::where('_id', $mongoId)->update([
                    'staff' => filter_var($item['staff'], FILTER_VALIDATE_BOOLEAN),
                    'user' => filter_var($item['user'], FILTER_VALIDATE_BOOLEAN)
                ]);
            }
        }

        return response()->json(['message' => 'Permissions updated successfully']);
    }
}
