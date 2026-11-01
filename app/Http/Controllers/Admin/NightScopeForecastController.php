<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NightScopeDateRequest;
use App\Services\Weather\ForecastService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class NightScopeForecastController extends Controller
{
    public function __invoke(NightScopeDateRequest $request, ForecastService $forecast): View
    {
        $date = CarbonImmutable::parse($request->validated('date') ?? 'today');

        return view('admin.nightscope.forecast', ['data' => $forecast->build($date)]);
    }
}
