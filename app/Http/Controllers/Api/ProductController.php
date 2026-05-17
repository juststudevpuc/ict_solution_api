<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Cloudinary\Api\Upload\UploadApi;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->query("q");
        $product = Product::where("name", "like", "%" . $query . "%")->get();

        return response()->json([
            "Query" => $query,
            // "data" => $product->load(["product_detail", "category"]),
             "data" => $product,
            "message" => "Search product successfully"
        ]);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $product = Product::query()->get();

        return response()->json([
            "data" => $product->load("category"),
            "message" => "Get product success"

        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $validate = $request->validate([
            "name" => "required|string|max:225|min:3",
            "description" => "required|string|min:3",
            "price" => "required|integer|min:0",
            "status" => "required|boolean",
            "image" => "nullable|file|max:2048",
            "category_id" => "nullable|string|exists:categories,_id"
        ]);
        $validate["status"] = filter_var($validate["status"], FILTER_VALIDATE_BOOLEAN);

        if ($request->hasFile("image")) {
            $upload = Cloudinary::uploadApi()->upload(
                $validate["image"]->getRealPath(),
                ["folder" => config("cloudinary.upload_present", "ict_solu_img")]
            );
            $validate["image_url"] = $upload["secure_url"];
            $validate["image_public_id"] = $upload["public_id"];
        }
        $product = new Product();
        $product->fill($validate);
        $product->save();
        return [
            "data" => $product,
            "message" => "Create product Success"
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                "data" => null,
                "message" => "Product not found"
            ], 404);
        }

        return response()->json([
            "data" => $product,
            "message" => "Get product success"
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                "data" => null,
                "message" => "Product not found"
            ], 404);
        }

        $validate = $request->validate([
            "name" => "sometimes|required|string|max:225|min:3",
            "description" => "sometimes|required|string|min:3",
            "price" => "sometimes|required|integer|min:0",
            "status" => "sometimes|required|boolean",
            "image" => "nullable|file|max:2048",
            "category_id" => "nullable|string|exists:categories,_id"

        ]);

        // Handle status conversion
        if (isset($validate["status"])) {
            $validate["status"] = filter_var($validate["status"], FILTER_VALIDATE_BOOLEAN);
        }

        // Handle image upload
        if ($request->hasFile("image")) {
            // Delete old image from Cloudinary if it exists
            if ($product->image_public_id) {
                (new UploadApi())->destroy($product->image_public_id);
            }

            $upload = (new UploadApi())->upload(
                $validate["image"]->getRealPath(),
                ["folder" => config("cloudinary.upload_present", "ict_solu_img")]
            );
            $validate["image_url"] = $upload["secure_url"];
            $validate["image_public_id"] = $upload["public_id"];
        }

        $product->fill($validate);
        $product->save();

        return response()->json([
            "data" => $product,
            "message" => "Update product success"
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                "data" => null,
                "message" => "Product not found"
            ], 404);
        }

        // Delete image from Cloudinary if it exists
        // Update this section in your destroy function
        if ($product->image_public_id) {
            Cloudinary::uploadApi()->destroy($product->image_public_id);
        }
        $product->delete();

        return response()->json([
            "data" => null,
            "message" => "Delete product success"
        ]);
    }
}
