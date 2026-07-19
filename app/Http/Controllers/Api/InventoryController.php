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
        $inventory = Inventory::with("product")
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            "message" => "Get inventory successfully",
            "data" => $inventory
        ]);
    }

    public function productHistory($productId)
    {
        $history = Inventory::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            "message" => "Get all product history successfully",
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
            'reference_id' => 'nullable',
            'remark'       => 'nullable'
        ]);

        $product = Product::findOrFail($validated['product_id']);

        // 🔥 FIX 1: Force strict integers for MongoDB
        $qty = (int) $validated['qty'];
        $currentStock = (int) $product->stock_qty;

        if ($validated['type'] === 'in') {
            $currentStock += $qty;
        } else {
            if ($currentStock < $qty) {
                return response()->json([
                    'message' => "Not enough stock to remove! (In Stock: {$currentStock})"
                ], 400);
            }
            $currentStock -= $qty;
        }

        // Save the updated stock back to the product
        $product->stock_qty = $currentStock;
        $product->save();

        // Write the receipt in our history book
        $validated['qty'] = $qty; // Save as strict int
        $validated['stock_left'] = $currentStock;
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
        $inventory = Inventory::find($id);

        if (!$inventory) {
            return response()->json([
                "message" => "Cannot find inventory by ID",
                "data" => null
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
        $inventory = Inventory::find($id);

        if (!$inventory) {
            return response()->json([
                "message" => "Cannot find inventory by ID",
            ], 404);
        }

        $validated = $request->validate([
            'type'       => 'required|in:in,out,adjustment',
            'qty'        => 'required|numeric|min:1',
            'reference_id' => 'nullable',
            'remark'       => 'nullable'
        ]);

        $product = Product::findOrFail($inventory->product_id);

        $oldQty = (int) $inventory->qty;
        $newQty = (int) $validated['qty'];
        $currentStock = (int) $product->stock_qty;

        // 🔥 FIX 2: Safely recalculate stock if admin changes a past record
        // First, undo the old transaction
        if ($inventory->type === 'in') {
            $currentStock -= $oldQty;
        } else {
            $currentStock += $oldQty;
        }

        // Second, apply the new transaction
        if ($validated['type'] === 'in') {
            $currentStock += $newQty;
        } else {
            if ($currentStock < $newQty) {
                return response()->json([
                    'message' => "Cannot update: Not enough stock! (Available: {$currentStock})"
                ], 400);
            }
            $currentStock -= $newQty;
        }

        // Save the corrected stock to the product
        $product->stock_qty = $currentStock;
        $product->save();

        // Update the history log
        $validated['qty'] = $newQty;
        $validated['stock_left'] = $currentStock;
        $inventory->fill($validated);
        $inventory->save();

        return response()->json([
            "message" => "Inventory record updated successfully",
            "data" => $inventory
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $inventory = Inventory::find($id);

        if (!$inventory) {
            return response()->json([
                "message" => "Cannot find Inventory record!",
            ], 404);
        }

        // 🔥 FIX 3: Revert the product stock before deleting the log
        $product = Product::find($inventory->product_id);

        if ($product) {
            $qty = (int) $inventory->qty;
            $currentStock = (int) $product->stock_qty;

            // If we are deleting an "in" record, we must remove that stock.
            if ($inventory->type === 'in') {
                $product->stock_qty = max(0, $currentStock - $qty);
            }
            // If we are deleting an "out" record, we must give the stock back.
            else {
                $product->stock_qty = $currentStock + $qty;
            }

            $product->save();
        }

        $inventory->delete();

        return response()->json([
            "message" => "Inventory record deleted and stock reverted successfully",
            "data" => $inventory
        ]);
    }
}
