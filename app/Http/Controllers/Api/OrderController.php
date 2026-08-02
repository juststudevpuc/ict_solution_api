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
        $referenceDate = $dateInput ? \Carbon\Carbon::parse($dateInput, 'UTC') : \Carbon\Carbon::now('UTC');

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

        // 🔥 NEW FIX: Create a 12-month skeleton array starting with $0
        $allMonths = [];
        for ($i = 1; $i <= 12; $i++) {
            // Using day 1 prevents month overflow bugs (e.g., Feb 30th)
            $monthName = \Carbon\Carbon::create(null, $i, 1)->format('M'); // Jan, Feb, Mar...
            $allMonths[$monthName] = [
                'title' => $monthName,
                'total' => 0
            ];
        }

        // Loop through the actual MongoDB results and overwrite the $0 for months with sales
        foreach ($summarySaleByMonth as $sale) {
            // Support both array and object returns depending on the Mongo driver version
            $title = is_array($sale) ? $sale['title'] : $sale->title;
            $saleTotal = is_array($sale) ? $sale['total'] : $sale->total;

            if (isset($allMonths[$title])) {
                $allMonths[$title]['total'] = $saleTotal;
            }
        }

        // Reset the associative array back to a simple index array so React understands it
        $finalSummary = array_values($allMonths);

        return response()->json([
            "sale_this_month" => [
                "total" => $total,
                "total_order" => $total_order,
            ],
            // Pass the newly filled timeline instead of the raw Mongo data
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
            $startDate = \Carbon\Carbon::parse($request->start_date)->startOfDay();
            $query->where('created_at', '>=', $startDate);
        }

        if ($request->has('end_date') && $request->end_date !== '') {
            $endDate = \Carbon\Carbon::parse($request->end_date)->endOfDay();
            $query->where('created_at', '<=', $endDate);
        }

        // --- 🔥 NEW: DAY / MONTH / YEAR FILTERS ---
        if ($request->has('year') && $request->year !== '') {
            $year = (int) $request->year;
            $month = $request->has('month') && $request->month !== '' ? (int) $request->month : null;
            $day = $request->has('day') && $request->day !== '' ? (int) $request->day : null;

            if ($month && $day) {
                // Filter by Exact Day
                $date = \Carbon\Carbon::create($year, $month, $day);
                $query->where('created_at', '>=', $date->copy()->startOfDay())
                    ->where('created_at', '<=', $date->copy()->endOfDay());
            } elseif ($month) {
                // Filter by Entire Month
                $date = \Carbon\Carbon::create($year, $month, 1);
                $query->where('created_at', '>=', $date->copy()->startOfMonth())
                    ->where('created_at', '<=', $date->copy()->endOfMonth());
            } else {
                // Filter by Entire Year
                $date = \Carbon\Carbon::create($year, 1, 1);
                $query->where('created_at', '>=', $date->copy()->startOfYear())
                    ->where('created_at', '<=', $date->copy()->endOfYear());
            }
        }

        // --- EXISTING STATUS FILTERS ---
        if ($request->has('status') && $request->status !== '') {
            if ($request->status === 'paid') {
                $query->whereRaw([
                    '$expr' => [
                        '$gte' => ['$total_paid', '$total_amount']
                    ]
                ]);
                $query->where('status', 'approved');
            } else if ($request->status === 'pending') {
                $query->whereRaw([
                    '$expr' => [
                        '$lt' => ['$total_paid', '$total_amount']
                    ]
                ]);
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

                // 🔥 EXPERT FIX: Handle MongoDB strict typing for numbers
                if (is_numeric($query)) {
                    // If they typed a number (like 1001), do an exact integer match
                    $q->where("order_no", (int)$query);
                } else {
                    // If they typed text, do a standard string search
                    $q->where("order_no", "like", "%" . $query . "%");
                }

                // Always search the customer name as a string
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
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validate the incoming request (Notice 'payment_slip' is added)
        $request->validate([
            "total_amount" => "required",
            "total_paid" => "required",
            "remark" => "nullable|max:255",
            "payment_method" => "required|string|max:255",
            "phone" => "required|string",
            "address" => "required|string",
            "payment_slip" => "nullable|image|max:2048", // 🔥 NEW: Validate the image
            "detail" => "required|array",
            "detail.*.product_id" => "required",
            "detail.*.price" => "required",
            "detail.*.qty" => "required",
            "detail.*.discount" => "required",
            "detail.*.total" => "required",
        ]);

        // 2. Generate Order Number: ORD00001
        $lastOrder = Order::orderBy("_id", "desc")->first();
        if ($lastOrder) {
            $lastNumber = substr($lastOrder->order_no, 3);
            $order_no = "ORD" . str_pad($lastNumber + 1, 5, "0", STR_PAD_LEFT);
        } else {
            $order_no = "ORD00001";
        }

        $user = auth()->user();

        // 🔥 NEW: 3. Handle Cloudinary Image Upload for Payment Slip
        $payment_slip_url = null;
        if ($request->hasFile("payment_slip")) {
            $upload = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadApi()->upload(
                $request->file("payment_slip")->getRealPath(),
                ["folder" => config("cloudinary.upload_present", "ict_solu_receipts")]
            );
            $payment_slip_url = $upload["secure_url"];
        }

        // 4. Create the Order
        $order = Order::create([
            "order_no" => $order_no,
            "user_id" => $user->_id,
            "customer_name" => $user->name,
            "phone" => $request->phone,
            "address" => $request->address,
            "total_amount" => $request->total_amount,
            "total_paid" => $request->total_paid,
            "remark" => $request->remark,
            "payment_method" => $request->payment_method,
            "status" => "pending",
            "payment_slip" => $payment_slip_url // 🔥 NEW: Save the Cloudinary URL to the database
        ]);

        // 5. Process Order Details and Reduce Stock
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

                // Reduce stock quantity
                $product = Product::find($item["product_id"]);
                if ($product) {
                    $currentQty = $product->qty;
                    $orderQty = $item["qty"];
                    $newQty = max(0, $currentQty - $orderQty);
                    $product->update(["qty" => $newQty]);
                }
            }

            // Broadcast alert (if you are using websockets)
            // broadcast(new \App\Events\OrderAlert($order))->toOthers();

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
        // 1. Eager load the relationship
        $order = Order::with('orderDetails')->findOrFail($id);

        // 2. 🔥 FIX: Force integers and show exact numbers in the error!
        foreach ($order->orderDetails as $item) {
            $productId = $item->product_id ?? $item['product_id'];

            // Force the order quantity to be a strict integer
            $qty = (int) ($item->qty ?? $item['qty']);

            $product = Product::findOrFail($productId);

            // Force the database stock to be a strict integer
            $currentStock = (int) $product->stock_qty;

            if ($currentStock < $qty) {
                return response()->json([
                    'message' => "Approve failed: Not enough stock for {$product->name}. (In Stock: {$currentStock} | Requested: {$qty})"
                ], 400);
            }
        }

        // 3. Process Inventory
        foreach ($order->orderDetails as $item) {
            $productId = $item->product_id ?? $item['product_id'];
            $qty = (int) ($item->qty ?? $item['qty']);

            $product = Product::findOrFail($productId);

            // Do strict integer math
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

        $duration = (int) ($request->duration_days ?? 30);

        $order->update([
            'status' => 'approved',
            'duration_days' => $duration,
            'approved_at' => \Carbon\Carbon::now('UTC'),
            'deadline_at' => \Carbon\Carbon::now('UTC')->addDays($duration),
        ]);

        return response()->json(['message' => 'Order approved successfully!'], 200);
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
