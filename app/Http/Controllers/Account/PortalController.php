<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Contracts\View\View;

/**
 * The pages of the customer portal. The page scripts draw from WK.portal (App\Support\Portal), which is built for
 * the signed-in customer only, so every order, file and thread on screen belongs to them.
 */
class PortalController extends Controller
{
    public function dashboard(): View
    {
        return view('account.dashboard');
    }

    public function orders(): View
    {
        return view('account.orders');
    }

    public function library(): View
    {
        return view('account.library');
    }

    public function invoices(): View
    {
        return view('account.invoices');
    }

    /** One order. Somebody else's order number looks exactly like one that does not exist. */
    public function order(Request $request, string $number): View|Response
    {
        $order = $this->mine($request, $number);
        if (! $order) {
            return $this->notFound();
        }

        $request->attributes->set('portal.focus', $order->number);

        return view('account.order', ['order' => $order]);
    }

    /** An invoice exists only for paid orders. */
    public function invoice(Request $request, string $number): View|Response
    {
        $order = $this->mine($request, $number);
        if (! $order || ! $order->isCounted()) {
            return $this->notFound();
        }

        $request->attributes->set('portal.focus', $order->number);

        return view('account.invoice', ['order' => $order]);
    }

    /** Old prototype links: order.html?id=WK-10471 and invoice.html?id=WK-10471 */
    public function legacy(Request $request, string $kind): RedirectResponse
    {
        $id = strtoupper(trim((string) $request->query('id')));
        $to = $kind === 'invoice' ? 'account.invoices' : 'account.orders';

        if (! preg_match('/^WK-\d+$/', $id)) {
            return redirect()->route($to, [], 301);
        }

        $url = route($to) . '/' . $id;
        $qs  = http_build_query($request->except('id'));

        return redirect()->to($qs ? $url . '?' . $qs : $url, 301);
    }

    private function mine(Request $request, string $number): ?Order
    {
        return Order::query()
            ->where('number', $number)
            ->where('customer_id', $request->attributes->get('customer')->id)
            ->first();
    }

    private function notFound(): Response
    {
        return response()->view('account.errors.404', [], 404);
    }
}
