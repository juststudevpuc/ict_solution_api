<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    // 1. Send all permissions to React
    public function index()
    {
        $permissions = Permission::all();
        return response()->json($permissions);
    }

    // 2. Receive the updated matrix and save it
    public function updateMatrix(Request $request)
    {
        $matrix = $request->input('matrix', []);

        foreach ($matrix as $item) {
            $id = $item['id'] ?? $item['_id'] ?? null;
            if ($id) {
                $updateData = [];
                if (isset($item['staff'])) {
                    $updateData['staff'] = filter_var($item['staff'], FILTER_VALIDATE_BOOLEAN);
                }
                if (isset($item['user'])) {
                    $updateData['user'] = filter_var($item['user'], FILTER_VALIDATE_BOOLEAN);
                }
                if (isset($item['admin'])) {
                    $updateData['admin'] = filter_var($item['admin'], FILTER_VALIDATE_BOOLEAN);
                }

                if (!empty($updateData)) {
                    Permission::where('id', $id)->update($updateData);
                }
            }
        }

        return response()->json(['message' => 'Permissions updated successfully']);
    }
}
