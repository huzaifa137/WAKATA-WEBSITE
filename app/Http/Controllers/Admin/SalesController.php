<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Order;
use App\Support\AdminSales;
use App\Support\OrderRuleException;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Shared helpers of the sales controllers (orders, payments, deliveries, customers). */
abstract class SalesController extends Controller
{
    protected function admin(Request $request): ?Admin
    {
        return $request->attributes->get('admin');
    }

    /** Run a staff action on one order: the rules' refusals become a plain 422 message, success returns the fresh order. */
    protected function act(Order $order, string $message, Closure $do): JsonResponse
    {
        try {
            $do();
        } catch (OrderRuleException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage(), 'order' => AdminSales::order($order->fresh())], 422);
        }

        return response()->json(['ok' => true, 'message' => $message, 'order' => AdminSales::order($order->fresh())]);
    }
}
