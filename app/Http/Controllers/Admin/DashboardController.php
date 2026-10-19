<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminInsights;
use Illuminate\View\View;

/** Module 10: the dashboard. The orders come as WK.sales (see the view), the rest of the numbers as WK.ins. */
class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', ['ins' => AdminInsights::dashboard()]);
    }
}
