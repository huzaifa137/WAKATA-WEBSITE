<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminInsights;
use Illuminate\View\View;

/** Module 10: the activity log. Everyone on the team can read it; nobody can edit it. */
class ActivityController extends Controller
{
    public function index(): View
    {
        return view('admin.activity', ['ins' => AdminInsights::activityPayload()]);
    }
}
