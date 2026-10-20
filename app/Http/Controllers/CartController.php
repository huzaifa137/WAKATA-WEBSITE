<?php

namespace App\Http\Controllers;

use App\Support\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON endpoints behind the shop's Add buttons and the cart page. The cart itself lives in the session (App\Support\Cart). */
class CartController extends Controller
{
    /** Add one or more products: { items: [{ type, id, fmt?, qty? }] }. */
    public function add(Request $request): JsonResponse
    {
        $request->validate(['items' => ['required', 'array', 'min:1', 'max:' . Cart::MAX_LINES]]);

        $cart   = Cart::current();
        $result = $cart->add($request->input('items'));

        if ($result['added'] === 0) {
            return response()->json([
                'ok'      => false,
                'message' => $result['out'] > 0 ? 'Sorry, that item is out of stock right now.' : 'Sorry, that item is not available right now.',
                'count'   => $cart->count(),
            ], 422);
        }

        return response()->json([
            'ok'      => true,
            'count'   => $cart->count(),
            'added'   => $result['added'],
            'skipped' => $result['skipped'],
            'limited' => $result['limited'],
            'message' => $result['limited'] > 0 ? 'We only have a few left, so your cart holds the most we can supply.' : null,
        ]);
    }

    /** Change the quantity of a line: { k, qty }. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['k' => ['required', 'string', 'max:300'], 'qty' => ['required', 'integer', 'min:1', 'max:' . Cart::MAX_QTY]]);

        $cart = Cart::current();
        $cart->setQty($data['k'], (int) $data['qty']);

        return $this->state($cart);
    }

    /** Remove a line: { k }. */
    public function remove(Request $request): JsonResponse
    {
        $data = $request->validate(['k' => ['required', 'string', 'max:300']]);

        $cart = Cart::current();
        $cart->remove($data['k']);

        return $this->state($cart);
    }

    public function clear(): JsonResponse
    {
        $cart = Cart::current();
        $cart->clear();

        return $this->state($cart);
    }

    private function state(Cart $cart): JsonResponse
    {
        return response()->json(['ok' => true, 'cart' => $cart->priced()]);
    }
}
