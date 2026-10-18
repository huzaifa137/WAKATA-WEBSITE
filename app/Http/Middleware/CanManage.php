<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Module 8: admin.can:sales (orders, deliveries, customers) and admin.can:payments (confirm and refund payments, fees,
 * payment methods). The roles are decided in Admin::canManageSales() and Admin::canManagePayments(). Runs after admin.auth.
 */
class CanManage
{
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $admin = $request->attributes->get('admin');

        $allowed = $admin && match ($area) {
            'sales'    => $admin->canManageSales(),
            'payments' => $admin->canManagePayments(),
            'inbox'    => $admin->canManageInbox(),
            'content'  => $admin->canManageContent(),
            'settings' => $admin->canManageSettings(),
            default    => false,
        };

        if (! $allowed) {
            $message = match ($area) {
                'payments' => 'Only a super admin or manager can handle payments.',
                'inbox'    => 'Your role can read messages and subscribers but not answer or change them.',
                'content'  => 'Your role can look at the website content but not change it.',
                'settings' => 'Only a super admin or manager can change settings.',
                default    => 'Your role can look at sales but not change them.',
            };

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : redirect()->back()->with('fail', $message);
        }

        return $next($request);
    }
}
