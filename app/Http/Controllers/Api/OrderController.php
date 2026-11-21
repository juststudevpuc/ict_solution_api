<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function getSale(Request $request)
    {
        // 1. Catch the date from React. If none is selected, default to 'now'.
        $dateInput = $request->query('date');
        $referenceDate = $dateInput ? Carbon::parse($dateInput, 'UTC') : Carbon::now('UTC');

        // 2. Calculate month based on the specific reference date
        $startOfMonth = $referenceDate->copy()->startOfMonth();
        $endOfMonth = $referenceDate->copy()->endOfMonth();

        $saleThisMonth = Order::where('status', 'approved')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total, COUNT(*) as total_order')
            ->first();

        $total = $saleThisMonth ? (float) $saleThisMonth->total : 0;
        $total_order = $saleThisMonth ? (int) $saleThisMonth->total_order : 0;

        // 3. Calculate the year based on the exact same reference date
        $startOfYear = $referenceDate->copy()->startOfYear();
        $endOfYear = $referenceDate->copy()->endOfYear();

        $summarySaleByMonth = Order::where('status', 'approved')
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->selectRaw('MONTH(created_at) as month, COALESCE(SUM(total_amount), 0) as total')
            ->groupByRaw('MONTH(created_at)')
            ->orderByRaw('MONTH(created_at) ASC')
            ->get();

        // 4. Create a 12-month skeleton array starting with $0
        $allMonths = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthName = Carbon::create(null, $i, 1)->format('M'); // Jan, Feb, Mar...
            $allMonths[$monthName] = [
                'title' => $monthName,
                'total' => 0,
            ];
        }

        // Loop through the actual results and overwrite the $0 for months with sales
        foreach ($summarySaleByMonth as $sale) {
            $monthNum = (int) $sale->month;
            if ($monthNum >= 1 && $monthNum <= 12) {
                $monthName = Carbon::create(null, $monthNum, 1)->format('M');
                if (isset($allMonths[$monthName])) {
                    $allMonths[$monthName]['total'] = (float) $sale->total;
                }
            }
        }

        // Reset the associative array back to a simple index array so React understands it
        $finalSummary = array_values($allMonths);

        return response()->json([
            "sale_this_month" => [
                "total" => $total,
                "total_order" => $total_order,
            ],
            "summary_sale_by_month" => $finalSummary,
            "message" => "Get summary sale Successfully"
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Order::with('orderDetails.product');

        $role = strtolower(auth()->user()->role ?? 'user');

        if (!in_array($role, ['admin', 'staff'])) {
            $query->where('user_id', auth()->id());
        }

        // --- EXISTING DATE RANGE FILTERS ---
        if ($request->has('start_date') && $request->start_date !== '') {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $query->where('created_at', '>=', $startDate);
        }

        if ($request->has('end_date') && $request->end_date !== '') {
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->where('created_at', '<=', $endDate);
        }

        // --- DAY / MONTH / YEAR FILTERS ---
        if ($request->has('year') && $request->year !== '') {
            $year = (int) $request->year;
            $month = $request->has('month') && $request->month !== '' ? (int) $request->month : null;
            $day = $request->has('day') && $request->day !== '' ? (int) $request->day : null;

            if ($month && $day) {
                // Filter by Exact Day
                $date = Carbon::create($year, $month, $day);
                $query->where('created_at', '>=', $date->copy()->startOfDay())
                    ->where('created_at', '<=', $date->copy()->endOfDay());
            } elseif ($month) {
                // Filter by Entire Month
                $date = Carbon::create($year, $month, 1);
                $query->where('created_at', '>=', $date->copy()->startOfMonth())
                    ->where('created_at', '<=', $date->copy()->endOfMonth());
            } else {
                // Filter by Entire Year
                $date = Carbon::create($year, 1, 1);
                $query->where('created_at', '>=', $date->copy()->startOfYear())
                    ->where('created_at', '<=', $date->copy()->endOfYear());
            }
        }

        // --- STATUS FILTERS ---
        if ($request->has('status') && $request->status !== '') {
            if ($request->status === 'paid') {
                $query->whereColumn('total_paid', '>=', 'total_amount');
                $query->where('status', 'approved');
            } elseif ($request->status === 'pending') {
                $query->whereColumn('total_paid', '<', 'total_amount');
            } else {
                $query->where('status', $request->status);
            }
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($orders, 200);
    }

    // search
    public function search(Request $request)
    {
        $query = $request->query("q");

        $orders = Order::with(["orderDetails.product"])
            ->where(function ($q) use ($query) {
                if (is_numeric($query)) {
                    $q->where("order_no", (int) $query);
                } else {
                    $q->where("order_no", "like", "%" . $query . "%");
                }

                $q->orWhere("customer_name", "like", "%" . $query . "%");
                $q->orWhere("phone", "like", "%" . $query . "%");
            })
            ->get();

        return response()->json([
            "Query" => $query,
            "data" => $orders,
            "message" => "Search order successfully"
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validate the incoming request
        $request->validate([
            "total_amount" => "required|numeric",
            "total_paid" => "required|numeric",
            "remark" => "nullable|max:255",
            "payment_method" => "required|string|max:255",
            "phone" => "required|string",
            "address" => "required|string",
            "payment_slip" => "nullable|image|max:2048",
            "detail" => "required|array",
            "detail.*.product_id" => "required|exists:products,id",
            "detail.*.price" => "required|numeric",
            "detail.*.qty" => "required|integer|min:1",
            "detail.*.discount" => "required|numeric",
            "detail.*.total" => "required|numeric",
        ]);

        $user = auth()->user();

        // 2. Handle Cloudinary Image Upload for Payment Slip
        $payment_slip_url = null;
        if ($request->hasFile("payment_slip")) {
            $upload = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadApi()->upload(
                $request->file("payment_slip")->getRealPath(),
                ["folder" => config("cloudinary.upload_present", "ict_solu_receipts")]
            );
            $payment_slip_url = $upload["secure_url"];
        }

        // 3. Process Order and Details inside DB Transaction
        $order = DB::transaction(function () use ($request, $user, $payment_slip_url) {
            // Generate Order Number: ORD00001
            $lastOrder = Order::orderBy("id", "desc")->first();
            if ($lastOrder) {
                $lastNumber = (int) substr($lastOrder->order_no, 3);
                $order_no = "ORD" . str_pad($lastNumber + 1, 5, "0", STR_PAD_LEFT);
            } else {
                $order_no = "ORD00001";
            }

            $order = Order::create([
                "order_no" => $order_no,
                "user_id" => $user->id,
                "customer_name" => $user->name,
                "phone" => $request->phone,
                "address" => $request->address,
                "total_amount" => $request->total_amount,
                "total_paid" => $request->total_paid,
                "remark" => $request->remark,
                "payment_method" => $request->payment_method,
                "status" => "pending",
                "payment_slip" => $payment_slip_url,
                "duration_days" => (int) $request->input('duration_days', 30),
            ]);

            foreach ($request->detail as $item) {
                OrderDetail::create([
                    "product_id" => $item["product_id"],
                    "order_id" => $order->id,
                    "price" => $item["price"],
                    "qty" => $item["qty"],
                    "discount" => $item["discount"],
                    "total" => $item["total"],
                ]);

                // Reduce stock quantity
                $product = Product::lockForUpdate()->find($item["product_id"]);
                if ($product) {
                    $currentQty = (int) $product->stock_qty;
                    $orderQty = (int) $item["qty"];
                    $newQty = max(0, $currentQty - $orderQty);
                    $product->update(["stock_qty" => $newQty]);
                }
            }

            return $order;
        });

        return response()->json([
            "data" => $order->load(["orderDetails.product", "user"]),
            "message" => "Create order success"
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                "message" => "Order is not found"
            ], 404);
        }

        return response()->json([
            "data" => $order->load(["orderDetails.product", "user"]),
            "message" => "get order success"
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                "message" => "Order is not found"
            ], 404);
        }

        $order->update($request->only([
            "order_no",
            "total_amount",
            "total_paid",
            "remark",
            "payment_method",
        ]));

        return response()->json([
            "data" => $order->load(["orderDetails.product"]),
            "message" => "Update order success"
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        return DB::transaction(function () use ($id) {
            $order = Order::find($id);

            if (!$order) {
                return response()->json([
                    "message" => "Order is not found"
                ], 404);
            }

            $order->orderDetails()->delete();
            $order->delete();

            return response()->json([
                "data" => $order,
                "message" => "delete order success"
            ], 200);
        });
    }

    public function approve(Request $request, string $id)
    {
        return DB::transaction(function () use ($id) {
            $order = Order::with('orderDetails')->lockForUpdate()->findOrFail($id);

            // 1. Check stock availability first
            foreach ($order->orderDetails as $item) {
                $productId = $item->product_id;
                $qty = (int) $item->qty;

                $product = Product::lockForUpdate()->findOrFail($productId);
                $currentStock = (int) $product->stock_qty;

                if ($currentStock < $qty) {
                    return response()->json([
                        'message' => "Approve failed: Not enough stock for {$product->name}. (In Stock: {$currentStock} | Requested: {$qty})"
                    ], 400);
                }
            }

            // 2. Process stock deduction and inventory logs
            foreach ($order->orderDetails as $item) {
                $productId = $item->product_id;
                $qty = (int) $item->qty;

                $product = Product::lockForUpdate()->findOrFail($productId);
                $product->stock_qty = (int) $product->stock_qty - $qty;
                $product->save();

                Inventory::create([
                    'product_id' => $product->id,
                    'type' => 'out',
                    'qty' => $qty,
                    'stock_left' => $product->stock_qty,
                    'order_id' => $order->id,
                    'remark' => 'Sold via order: ' . $order->order_no,
                ]);
            }

            $duration = (int) $order->duration_days;

            $order->update([
                'status' => 'approved',
                'duration_days' => $duration,
                'approved_at' => Carbon::now('UTC'),
                'deadline_at' => Carbon::now('UTC')->addDays($duration),
            ]);

            return response()->json(['message' => 'Order approved successfully!'], 200);
        });
    }

    public function reject(string $id)
    {
        $order = Order::findOrFail($id);
        $order->status = 'rejected';
        $order->save();

        return response()->json(['message' => 'Order rejected!'], 200);
    }

    /**
     * CUSTOMER ACTION: Request a refund
     */
    public function requestRefund(string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order->status = 'refund_requested';
        $order->save();

        return response()->json([
            'message' => 'Refund requested successfully. Please wait for admin approval.',
            'data' => $order
        ], 200);
    }

    /**
     * ADMIN ACTION: Approve or Reject the refund
     */
    public function processRefund(Request $request, string $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject'
        ]);

        return DB::transaction(function () use ($request, $id) {
            $order = Order::with('orderDetails')->lockForUpdate()->find($id);

            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            if ($request->action === 'approve') {
                $order->status = 'refunded';

                // Restock inventory
                foreach ($order->orderDetails as $item) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $qty = (int) $item->qty;
                        $product->stock_qty = (int) $product->stock_qty + $qty;
                        $product->save();

                        Inventory::create([
                            'product_id' => $product->id,
                            'type' => 'in',
                            'qty' => $qty,
                            'stock_left' => $product->stock_qty,
                            'order_id' => $order->id,
                            'remark' => 'Restocked via refund for order: ' . $order->order_no,
                        ]);
                    }
                }

                $message = 'Refund approved and inventory restocked!';
            } else {
                $order->status = 'approved';
                $message = 'Refund request rejected.';
            }

            $order->save();

            return response()->json([
                'message' => $message,
                'data' => $order
            ], 200);
        });
    }
}
