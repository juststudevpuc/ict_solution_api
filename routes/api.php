<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StaffController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==========================================
// 1. PUBLIC ROUTES (No Login Required)
// ==========================================
Route::post("/register", [AuthController::class, "register"]);
Route::post("/login", [AuthController::class, "login"]);

// Anyone can view products
Route::get("/product", [ProductController::class, "index"]);


Route::prefix("inventory")->group(function () {
    // Route::get("/search", [OrderController::class, "search"]);
    Route::get("/", [InventoryController::class, "index"]);
    Route::post("/", [InventoryController::class, "store"]);
    Route::get('history/{productId}', [InventoryController::class, 'productHistory']);
    Route::get("/{id}", [InventoryController::class, "show"]);
    Route::put("/{id}", [InventoryController::class, "update"]);
    Route::delete("/{id}", [InventoryController::class, "destroy"]);

    // Route::patch('/{id}/approve', [InventoryController::class, 'approve']);
    // Route::patch('/{id}/reject', [InventoryController::class, 'reject']);
});


// ==========================================
// 2. AUTHENTICATED USER ROUTES
// ==========================================
Route::middleware("auth:sanctum")->group(function () {
    Route::post("/logout", [AuthController::class, "logout"]);
    Route::get("/me", [AuthController::class, "me"]);
    // Add this new route for updating the profile!
    Route::post("/user/update", [AuthController::class, "updateProfile"]);

    // User Orders
    Route::post("/order", [OrderController::class, "store"]);
    Route::get("/order", [OrderController::class, "index"]); // Fixed: Now uses OrderController!

    Route::post('/order/{id}/request-refund', [OrderController::class, 'requestRefund']);
});

Route::prefix("category")->group(function () {
    Route::get("/", [CategoryController::class, "index"]);
    Route::get("/{id}", [CategoryController::class, "show"]);
    Route::post("/", [CategoryController::class, "store"]);
    Route::put("/{id}", [CategoryController::class, "update"]);
    Route::delete("/{id}", [CategoryController::class, "destroy"]);
});

Route::apiResource('staff', StaffController::class);

Route::get('/permissions', [PermissionController::class, 'index']);
Route::post('/permissions/update-matrix', [PermissionController::class, 'updateMatrix']);


// ==========================================
// 3. ADMIN ROUTES (Protected by checkAdmin)
// ==========================================
// We add the "admin" prefix so it doesn't conflict with normal users!
// Example: POST /api/admin/product
Route::prefix("admin")->middleware(["auth:sanctum", "checkAdmin"])->group(function () {

    Route::get("/user", [AuthController::class, "index"]);

    Route::get("/getSale", [OrderController::class, "getSale"]);

    Route::post('/order/{id}/process-refund', [OrderController::class, 'processRefund']);

    // Admin Products
    Route::prefix("product")->group(function () {
        Route::get("/search", [ProductController::class, "search"]);
        Route::get("/", [ProductController::class, "index"]);
        Route::get("/{id}", [ProductController::class, "show"]);
        Route::post("/", [ProductController::class, "store"]);
        Route::put("/{id}", [ProductController::class, "update"]);
        Route::delete("/{id}", [ProductController::class, "destroy"]);
    });

    // Admin Orders
    Route::prefix("order")->group(function () {
        Route::get("/search", [OrderController::class, "search"]);
        Route::get("/", [OrderController::class, "index"]);
        Route::get("/{id}", [OrderController::class, "show"]);
        Route::put("/{id}", [OrderController::class, "update"]);
        Route::delete("/{id}", [OrderController::class, "destroy"]);

        Route::patch('/{id}/approve', [OrderController::class, 'approve']);
        Route::patch('/{id}/reject', [OrderController::class, 'reject']);
    });

    // Route::prefix("inventory")->group(function () {
    //     // Route::get("/search", [OrderController::class, "search"]);
    //     Route::get("/", [InventoryController::class, "index"]);
    //     Route::get("/{id}", [InventoryController::class, "show"]);
    //     Route::put("/{id}", [InventoryController::class, "update"]);
    //     Route::delete("/{id}", [InventoryController::class, "destroy"]);

    //     // Route::patch('/{id}/approve', [InventoryController::class, 'approve']);
    //     // Route::patch('/{id}/reject', [InventoryController::class, 'reject']);
    // });
});
