<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardOverviewService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardOverviewService $overview): View
    {
        return view('admin.dashboard', $overview->build());
    }
}
