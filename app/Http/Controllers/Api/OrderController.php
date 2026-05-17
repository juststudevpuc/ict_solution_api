<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function getSale()
    {
        // current month
        $startOfMonth = Carbon::now("UTC")->startOfMonth();
        $endOfMonth = Carbon::now("UTC")->endOfMonth();

        $saleThisMonth = Order::raw(function ($collection) use ($startOfMonth, $endOfMonth) {
            return $collection->aggregate([
                [
                    '$match' => [
                        'create_at' => [
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

        // current year
        $startOfYear = Carbon::now("UTC")->startOfYear();
        $endOfYear = Carbon::now("UTC")->endOfYear();

        $summarySaleByMonth = Order::raw(function ($collection) use ($startOfYear, $endOfYear) {
            return $collection->aggregate([
                [
                    '$match' => [
                        'create_at' => [
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
    public function index()
    {
        // 1. Start building the query (don't get the data yet)
        $query = Order::with('orderDetails.product')->latest();

        if (auth()->user()->role !== 'admin') {
            // If they are NOT an admin, lock the query to only their ID
            $query->where('user_id', auth()->id());
        }
        $orders = $query->get();
        return response()->json($orders);
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
            "total_amount" => $request->total_amount,
            "total_paid" => $request->total_paid,
            "remark" => $request->remark,
            "payment_method" => $request->payment_method,
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
}
