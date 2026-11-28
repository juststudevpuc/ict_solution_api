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
        $permissions = Permission::orderBy('id')->get();
        return response()->json($permissions);
    }

    // 2. Receive the updated matrix and save it
    public function updateMatrix(Request $request)
    {
        // Only full admins (not staff) should modify role permissions
        if (!$request->user()?->isAdmin()) {
            return response()->json(['message' => 'Forbidden: Admin access required.'], 403);
        }

        $validated = $request->validate([
            'matrix' => ['required', 'array'],
            'matrix.*.id' => ['required', 'integer', 'exists:permissions,id'],
            'matrix.*.admin' => ['sometimes', 'boolean'],
            'matrix.*.staff' => ['sometimes', 'boolean'],
            'matrix.*.user' => ['sometimes', 'boolean'],
        ]);

        foreach ($validated['matrix'] as $item) {
            $permission = Permission::find($item['id']);
            if (!$permission) {
                continue;
            }

            $updateData = [];

            if (array_key_exists('staff', $item)) {
                $updateData['staff'] = (bool) $item['staff'];
            }
            if (array_key_exists('user', $item)) {
                $updateData['user'] = (bool) $item['user'];
            }
            if (array_key_exists('admin', $item)) {
                // Never allow admin to lock themselves out of Settings
                $updateData['admin'] = $permission->key === 'super_admin_only_setting'
                    ? true
                    : (bool) $item['admin'];
            }

            if (!empty($updateData)) {
                $permission->update($updateData);
            }
        }

        return response()->json([
            'message' => 'Permissions updated successfully',
            'data' => Permission::orderBy('id')->get(),
        ]);
    }
}
