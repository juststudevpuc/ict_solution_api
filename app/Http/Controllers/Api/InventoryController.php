<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $inventory = Inventory::with("product")
            ->orderBy('created_at', 'desc')->paginate(10);;
        return response()->json([
            "message" => "get inventory successfully",
            "data" => $inventory
        ]);
    }
    public function productHistory($productId)
    {
        $history  = Inventory::where('product_id', $productId)
            ->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            "message" => "get all product history successfully",
            "data" => $history
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required',
            'type'       => 'required|in:in,out,adjustment',
            'qty'        => 'required|numeric|min:1',
            'reference_id' => 'nullable', // Added these so they get purified too!
            'remark'       => 'nullable'
        ]);


        $product = Product::findOrFail($validated['product_id']);

        // Do the math based on if stock is coming IN or going OUT
        if ($validated['type'] === 'in') {
            $product->stock_qty += $validated['qty'];
        } else {
            if ($product->stock_qty < $validated['qty']) {
                return response()->json(['message' => 'Not enough stock to remove!'], 400);
            }
            $product->stock_qty -= $validated['qty'];
        }

        $product->save();

        // Write the receipt in our history book!
        $validated['stock_left'] = $product->stock_qty;
        $validated['reference_id'] = $validated['reference_id'] ?? 'Manual';
        $validated['remark'] = $validated['remark'] ?? 'Admin adjust stock';

        $inventory = Inventory::create($validated);

        return response()->json([
            'message' => 'Inventory updated successfully!',
            'data' => $inventory
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $inventory = Inventory::find($id);

        if (!$inventory) {
            return response()->json([
                "message" => "Can not inventory by ID",
                "data" => $inventory
            ], 404);
        }
        return response()->json([
            "message" => "Find Inventory by ID successfully",
            "data" => $inventory
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $inventory = Inventory::find($id);

        if (!$inventory) {
            return response()->json([
                "message" => "Can not inventory by ID",
            ], 404);
        }
        $validated = $request->validate([
            'product_id' => 'required',
            'type'       => 'required|in:in,out,adjustment',
            'qty'        => 'required|numeric|min:1',
            'reference_id' => 'nullable', // Added these so they get purified too!
            'remark'       => 'nullable'
        ]);

        $inventory->fill($validated);
        $inventory->save();
        return response()->json([
            "message" => "Update data successfully",
            "data" => $inventory
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $inventory = Inventory::find($id);

        if (!$inventory) {
            return response()->json([
                "message" => "Can not find Inventory!",
            ], 404);
        }

        $inventory->delete();
        return response()->json([
            "message" => "Delete successfully",
            "data" => $inventory
        ]);
    }
}
