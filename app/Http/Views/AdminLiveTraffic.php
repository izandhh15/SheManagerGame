<?php

namespace App\Http\Views;

use App\Modules\Analytics\Services\LiveTrafficService;
use Illuminate\Http\Request;

class AdminLiveTraffic
{
    public function __invoke(Request $request, LiveTrafficService $traffic)
    {
        return view('admin.live-traffic', ['snapshot' => $traffic->getSnapshot()]);
    }
}
