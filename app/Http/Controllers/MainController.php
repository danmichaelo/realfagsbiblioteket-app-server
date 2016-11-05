<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client as Http;
use Illuminate\Http\Request;

class MainController extends Controller
{
    public function status(Request $request)
    {
        $appVersion = $request->input('app_version');
        $platform = $request->input('platform');
        $platformVersion = $request->input('platform_version');
        $device = $request->input('device');

        if (!is_null($device)) {
            app('db')->insert('INSERT INTO app_events (app_version, platform, platform_version, device, event_type, event_time) VALUES (?, ?, ?, ?, ?, current_timestamp)',
                [$appVersion, $platform, $platformVersion, $device, 'status']
            );
        }

        return response()->json([
            'status' => 'ok'
        ]);
    }

    public function search(Request $request, Http $http)
    {
        $appVersion = $request->input('app_version');
        $platform = $request->input('platform');
        $platformVersion = $request->input('platform_version');
        $device = $request->input('device');

        $query = $request->input('query');
        $start = $request->input('start', 1);
        $institution = $request->input('institution', 'UBO');
        $library = $request->input('library', 'ubo1030310,ubo1030317,ubo1030500');
        $sort = $request->input('sort', 'date');
        $material = $request->input('material', 'print-books,books');

        if (!is_null($device)) {
            app('db')->insert('INSERT INTO app_events (app_version, platform, platform_version, device, event_type, event_time, event_data) VALUES (?, ?, ?, ?, ?, current_timestamp, ?)',
                [$appVersion, $platform, $platformVersion, $device, 'search', json_encode(['query' => $query])]
            );
        }

        $res = $http->request('GET', 'https://lsm.biblionaut.net/primo/search', [
            'query' => [
                'query' => $query,
                'start' => $start,
                'institution' => $institution,
                'library' => $library,
                'sort' => $sort,
                'material' => $material,
            ]
        ]);

        return response($res->getBody(), 200)
            ->header('Content-Type', 'application/json');
    }
}
