<?php

namespace App\Http\Views;

use App\Modules\Analytics\Services\LiveTrafficService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLiveTrafficData
{
    public function __invoke(Request $request, LiveTrafficService $traffic): JsonResponse
    {
        return response()->json($traffic->getSnapshot());
    }
}
