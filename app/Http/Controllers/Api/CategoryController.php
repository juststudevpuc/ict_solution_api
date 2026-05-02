<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    //
    public function index()
    {
        $category = Category::query()->get();
        return response()->json([
            "data" => $category,
            "message" => "get category successfully"
        ]);
    }
    public function store(Request $request)
    {
        $validate = $request->validate([
            "name" => "required|string|max:225|min:3",
            "description" => "required|string|max:225|min:3",
            "status" => "required|boolean",
        ]);
        // This way it handles "1", "0", true, false consistently.
        $validate["status"] = filter_var($validate["status"], FILTER_VALIDATE_BOOLEAN);

        $category = new Category();
        $category->fill($validate);
        $category->save();
        return [
            "data" => $category,
            "message" => "Create category success"
        ];
    }

    public function show($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                "data" => $category,
                "message" => "Category not found"
            ], 404);
        }

        return response()->json([
            "data" => $category,
            "message" => "Get category successfully"
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                "data" => $category,
                "message" => "Category not found"
            ], 404);
        }

        $validate = $request->validate([
            "name" => "sometimes|required|string|max:225|min:3",
            "description" => "sometimes|required|string|max:225|min:3",
            "status" => "sometimes|required|boolean",
        ]);

        // Handle boolean filtering if status is provided
        if (isset($validate["status"])) {
            $validate["status"] = filter_var($validate["status"], FILTER_VALIDATE_BOOLEAN);
        }

        $category->fill($validate);
        $category->save();

        return response()->json([
            "data" => $category,
            "message" => "Update category success"
        ]);
    }

    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                "data" => $category,
                "message" => "Category not found"
            ], 404);
        }

        $category->delete();

        return response()->json([
            "data" => $category,
            "message" => "Delete category success"
        ]);
    }
}
