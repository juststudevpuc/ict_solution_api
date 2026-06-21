<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;

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

        $saleThisMonth = Order::raw(function ($collection) use ($startOfMonth, $endOfMonth) {
            return $collection->aggregate([
                [
                    '$match' => [
                        'status' => 'approved',
                        'created_at' => [
                            '$gte' => new \MongoDB\BSON\UTCDateTime($startOfMonth),
                            '$lte' => new \MongoDB\BSON\UTCDateTime($endOfMonth),
                        ]
                    ]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        'total' => ['$sum' => ['$toDouble' => '$total_amount']],
                        'total_order' => ['$sum' => 1],
                    ]
                ],
            ]);
        });

        $total = isset($saleThisMonth[0]) ? $saleThisMonth[0]->total : 0;
        $total_order = isset($saleThisMonth[0]) ? $saleThisMonth[0]->total_order : 0;

        // 3. Calculate the year based on the exact same reference date
        $startOfYear = $referenceDate->copy()->startOfYear();
        $endOfYear = $referenceDate->copy()->endOfYear();

        $summarySaleByMonth = Order::raw(function ($collection) use ($startOfYear, $endOfYear) {
            return $collection->aggregate([
                [
                    '$match' => [
                        'status' => 'approved',
                        'created_at' => [
                            '$gte' => new \MongoDB\BSON\UTCDateTime($startOfYear),
                            '$lte' => new \MongoDB\BSON\UTCDateTime($endOfYear),
                        ]
                    ]
                ],
                [
                    '$group' => [
                        '_id' => [
                            'month' => ['$month' => '$created_at'],
                            'year' => ['$year' => '$created_at'],
                        ],
                        'total' => ['$sum' => ['$toDouble' => '$total_amount']]
                    ]
                ],
                [
                    '$sort' => ['_id.month' => 1]
                ],
                [
                    '$project' => [
                        'title' => [
                            '$dateToString' => [
                                'format' => '%b',
                                'date' => [
                                    '$dateFromParts' => [
                                        'month' => '$_id.month',
                                        'year' => '$_id.year',
                                        'day' => 1,
                                    ]
                                ]
                            ]
                        ],
                        'total' => 1,
                        '_id' => 0
                    ]
                ]
            ]);
        });

        return response()->json([
            "sale_this_month" => [
                "total" => $total,
                "total_order" => $total_order,
            ],
            "summary_sale_by_month" => $summarySaleByMonth,
            "message" => "Get summary sale Successfully"
        ]);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $query = Order::with('orderDetails.product');

        // Extract the role safely
        $role = strtolower(auth()->user()->role ?? 'user');

        // If they are NOT an admin and NOT a staff member, lock it to their ID
        if (!in_array($role, ['admin', 'staff'])) {
            $query->where('user_id', auth()->id());
        }
        // $query = Order::query();

        if ($request->has('start_date') && $request->start_date !== '') {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $query->where('created_at', '>=', $startDate);
        }

        if ($request->has('end_date') && $request->end_date !== '') {
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->where('created_at', '<=', $endDate);
        }

        if ($request->has('status') && $request->status !== '') {
            if ($request->status === 'paid') {
                // 1. Math check: is total_paid >= total_amount?
                $query->whereRaw([
                    '$expr' => [
                        '$gte' => ['$total_paid', '$total_amount']
                    ]
                ]);
                // 2. NEW SECURITY LOCK: Ensure it is ALSO approved!
                $query->where('status', 'approved');
            } else if ($request->status === 'pending') {
                // Math check: is total_paid < total_amount?
                $query->whereRaw([
                    '$expr' => [
                        '$lt' => ['$total_paid', '$total_amount']
                    ]
                ]);
            } else {
                // If React explicitly asks for 'approved' or 'rejected'
                $query->where('status', $request->status);
            }
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $orders
        ], 200);
    }
    // search
    public function search(Request $request)
    {
        $query = $request->query("q");

        // ✅ FIXED: Search by order_no OR customer_name
        $orders = Order::where("order_no", "like", "%" . $query . "%")
            ->orWhere("customer_name", "like", "%" . $query . "%")
            ->get();
        $orders->load(["orderDetails.product"]);

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
        //
        $request->validate([
            "total_amount" => "required",
            "total_paid" => "required",
            "remark" => "nullable|max:255",
            "payment_method" => "required|string|max:255",
            "phone" => "required|string",    // 🔥 ADDED
            "address" => "required|string",  // 🔥 ADDED
            "detail" => "required|array",

            "detail.*.product_id" => "required",
            "detail.*.price" => "required",
            "detail.*.qty" => "required",
            "detail.*.discount" => "required",
            "detail.*.total" => "required",
        ]);

        //order_no : ORD00001

        $lastOrder = Order::orderBy("_id", "desc")->first();
        if ($lastOrder) {
            $lastNumber = substr($lastOrder->order_no, 3);
            $order_no = "ORD" . str_pad($lastNumber + 1, 5, "0", STR_PAD_LEFT);
        } else {
            $order_no = "ORD00001";
        }

        $user = auth()->user();


        $order = Order::create([
            "order_no" => $order_no,
            "user_id" => $user->_id,
            "customer_name" => $user->name,
            "phone" => $request->phone,      // 🔥 ADDED
            "address" => $request->address,  // 🔥 ADDED
            "total_amount" => $request->total_amount,
            "total_paid" => $request->total_paid,
            "remark" => $request->remark,
            "payment_method" => $request->payment_method,
            "status" => "pending"
        ]);

        if ($order) {
            foreach ($request->detail as $item) {
                OrderDetail::create([
                    "product_id" => $item["product_id"],
                    "order_id" => $order->_id,
                    "price" => $item["price"],
                    "qty" => $item["qty"],
                    "discount" => $item["discount"],
                    "total" => $item["total"],
                ]);
                // get curr qty in product
                $product = Product::find($item["product_id"]);

                $currentQty = $product->qty;
                $orderQty = $item["qty"];

                $newQty = max(0, $currentQty - $orderQty);
                $product->update(["qty" => $newQty]);
            }
            broadcast(new \App\Events\OrderAlert($order))->toOthers();

            return response()->json([
                "data" => $order->load(["orderDetails", "user"]),
                "message" => "Create order success"
            ], 201);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                "message" => "Order is not found"
            ], 404);
        }
        return response()->json([
            "data" => $order->load(["orderDetails", "deliveries"]),
            "message" => "get order success"
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
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
            "data" => $order->load(["orderDetails"]),
            "message" => "Update order success"
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                "message" => "Order is not found"
            ], 404);
        }
        // delete order
        $order->delete();
        // delete order details
        $order->orderDetails()->delete();

        return response()->json([
            "data" => $order,
            "message" => "delete order success"
        ], 200);
    }

    public function approve(Request $request, string $id)
    {
        $order = Order::findOrFail($id);
        // for checking stock
        foreach ($order->order_details as $item) {
            $product = Product::findOrFail($item['product_id']);

            if ($product->stock_qty < $item['qty']) {
                return response()->json([
                    'message' => "Approve failed: Not enough stock for {$product->name}. Only {$product->stock_qty} left."
                ]);
            }
        }
        // for reduce stock amount
        foreach ($order->order_details as $item) {
            $product = Product::findOrFail($item['product_id']);
            $product->stock_qty -= $item['qty'];
            $product->save();
        }

        Inventory::create([
            'product_id' => $product->id,
            'type' => 'out',
            'qty' => $item['qty'],
            'stock_left' => $product->stock_qty,
            'order_id' => $order->id,
            'remark' => 'Sold via order',
        ]);

        // 1. Catch the custom days from React (Default to 30 if none provided)
        $duration = $request->duration_days ?? 30;

        // 2. Start the clock using DAYS!
        $order->status = 'approved';
        $order->duration_days = $duration;
        $order->approved_at = Carbon::now('UTC');
        $order->deadline_at = Carbon::now('UTC')->addDays($duration); // changed to addDays!

        $order->save();

        return response()->json(['message' => 'Order approved with custom duration!'], 200);
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

        // Change the status to alert the Admin
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

        $order = Order::with('orderDetails')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($request->action === 'approve') {
            $order->status = 'refunded';

            // --- INVENTORY RESTOCK LOGIC ---
            // If you DO NOT want to give the items back to the store, delete this loop!
            foreach ($order->orderDetails as $item) {
                $product = \App\Models\Product::find($item->product_id);
                if ($product) {
                    $product->update(['qty' => $product->qty + $item->qty]);
                }
            }
            // -------------------------------

            $message = 'Refund approved and inventory restocked!';
        } else {
            // If rejected, put it back to approved/paid status
            $order->status = 'approved';
            $message = 'Refund request rejected.';
        }

        $order->save();

        return response()->json([
            'message' => $message,
            'data' => $order
        ], 200);
    }
}
