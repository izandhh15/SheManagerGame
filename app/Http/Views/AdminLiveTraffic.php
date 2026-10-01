<?php

namespace App\Http\Views;

use App\Modules\Analytics\Services\LiveTrafficService;
use Illuminate\Http\Request;

class AdminLiveTraffic
{
    public function __invoke(Request $request, LiveTrafficService $traffic)
    {
        // Las traducciones para el JS se construyen aquí (no con @json([...]) multilínea en el Blade,
        // que rompe el parser de directivas de Blade).
        $i18n = [
            'activity' => [
                'home' => __('admin.live_activity_home'),
                'playing' => __('admin.live_activity_playing'),
                'creating' => __('admin.live_activity_creating'),
                'entering' => __('admin.live_activity_entering'),
                'browsing' => __('admin.live_activity_browsing'),
            ],
            'device' => [
                'desktop' => __('admin.live_device_desktop'),
                'mobile' => __('admin.live_device_mobile'),
                'tablet' => __('admin.live_device_tablet'),
            ],
            'anonymous' => __('admin.live_anonymous_label'),
            'noOne' => __('admin.live_no_one'),
            'updatedAgo' => __('admin.live_updated_ago'),
            'visits' => __('admin.live_visits'),
            'uniques' => __('admin.live_uniques'),
        ];

        return view('admin.live-traffic', [
            'snapshot' => $traffic->getSnapshot(),
            'i18n' => $i18n,
        ]);
    }
}
