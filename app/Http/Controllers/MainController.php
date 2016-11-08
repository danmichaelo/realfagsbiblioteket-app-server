<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client as Http;
use Illuminate\Http\Request;

class MainController extends Controller
{

    protected function trackEvent($eventType, Request $request, $data = null)
    {
        $appVersion = $request->input('app_version');
        $platform = $request->input('platform');
        $platformVersion = $request->input('platform_version');
        $device = $request->input('device');

        if (!is_null($device)) {
            app('db')->insert('INSERT INTO app_events (event_type, app_version, platform, platform_version, device, event_time, event_data) VALUES (?, ?, ?, ?, ?, current_timestamp, ?)',
                [$eventType, $appVersion, $platform, $platformVersion, $device, json_encode($data)]
            );
        }
    }

    public function status(Request $request)
    {
        $this->trackEvent('status', $request);

        return response()->json([
            'status' => 'ok'
        ]);
    }

    public function search(Request $request, Http $http)
    {
        $query = $request->input('query');
        $start = $request->input('start', 1);
        $institution = $request->input('institution', 'UBO');
        $library = $request->input('library', 'ubo1030310,ubo1030317,ubo1030500');
        $sort = $request->input('sort', 'date');
        $material = $request->input('material', 'print-books,books');

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

        $json = json_decode($res->getBody());

        $this->trackEvent('search', $request, [
            'query' => $query,
            'total_results' => $json->total_results,
        ]);

        return response()->json($json);
    }

    public function group(Request $request, Http $http, $id)
    {
        $res = $http->request('GET', 'https://lsm.biblionaut.net/primo/groups/' . $id);

        $this->trackEvent('group', $request, ['id' => $id]);

        return response($res->getBody(), 200)
            ->header('Content-Type', 'application/json');
    }

    public function record(Request $request, Http $http, $id)
    {
        $res = $http->request('GET', 'https://lsm.biblionaut.net/primo/records/' . $id);

        $this->trackEvent('record', $request);

        return response($res->getBody(), 200)
            ->header('Content-Type', 'application/json');
    }
}
