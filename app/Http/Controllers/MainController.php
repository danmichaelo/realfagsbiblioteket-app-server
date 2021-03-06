<?php

namespace App\Http\Controllers;

use App\Exceptions\SearchFailed;
use GuzzleHttp\Client as Http;
use GuzzleHttp\Exception\ClientException;

use Illuminate\Http\Request;
use App\Record;

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
        $this->trackEvent('status', $request, [
            'language' => $request->input('language'),
        ]);

        return response()->json([
            'status' => 'ok'
        ]);
    }

    public function getScope(Request $request)
    {
        $scope = null;
        $institution = null;
        $library = null;
        switch ($request->input('scope')) {
            case 'BIBSYS':
                $scope = 'BIBSYS_ILS';
                break;

            case 'UBO':
                $scope = 'UBO';
                $institution = 'UBO';
                break;

            default:
                $scope = 'UBO';
                $institution = 'UBO';
                $library = 'ubo1030310,ubo1030317,ubo1030500';
        }
        return [$scope, $institution, $library];
    }

    public function search(Request $request, Http $http)
    {
        $query = $request->input('query');
        list($scope, $institution, $library) = $this->getScope($request);

        $start = $request->input('start', 1);
        $sort = $request->input('sort', 'date');
        $material = $request->input('material', 'print-books');
        $raw = $request->input('raw');
        $repr = $request->input('repr', 'full');
        $apiVersion = intval($request->input('apiVersion', '1'));

        $t0 = microtime(true);

        try {
            $res = $http->request('GET', 'https://ub-lsm.uio.no/primo/search', [
                'query' => [
                    'query' => $query,
                    'start' => $start,
                    'scope' => $scope,
                    'institution' => $institution,
                    'library' => $library,
                    'sort' => $sort,
                    'material' => $material,
                    'raw' => $raw,
                    'repr' => $repr,
                ]
            ]);
        } catch (ClientException $e) {
            $details = "Server error from https://ub-lsm.uio.no/primo/search\n\n" . \GuzzleHttp\Psr7\str($e->getRequest()) . "\n" . \GuzzleHttp\Psr7\str($e->getResponse());
            \Log::error($details);

            $errorMsg = $e->getMessage();

            if ($e->hasResponse()) {
                $json = json_decode($e->getResponse()->getBody());
                $errorMsg = $json->error;
            }

            \Sentry\captureException(new SearchFailed($errorMsg, 0, $e));

            return response()->json([
                'error' => $errorMsg,
            ], 400);
        }

        if ($raw) {
            echo $res->getBody();die;
        }

        $body = json_decode($res->getBody());
        foreach ($body->results as $record) {
            Record::process($record, $apiVersion);
        }

        $t1 = microtime(true) - $t0;
        $t1 = round($t1 * 1000);

        $this->trackEvent('search', $request, [
            'query' => $query,
            'total_results' => $body->total_results,
            'msecs' => $t1,
        ]);

        $body->msecs = $t1;

        return response()->json($body);
    }

    public function group(Request $request, Http $http, $id)
    {
        list($scope, $institution, $library) = $this->getScope($request);
        $apiVersion = intval($request->input('apiVersion', '1'));

        $res = $http->request('GET', 'https://ub-lsm.uio.no/primo/groups/' . $id, [
            'query' => [
                'scope' => $scope,
                'institution' => $institution,
            ],
        ]);
        $body = json_decode($res->getBody());
        foreach ($body->result->records as $record) {
            Record::process($record, $apiVersion);
        }

        $this->trackEvent('group', $request, ['id' => $id]);

        return response()->json($body);
    }

    public function record(Request $request, Http $http, $id)
    {
        $res = $http->request('GET', 'https://ub-lsm.uio.no/primo/records/' . $id);
        $apiVersion = intval($request->input('apiVersion', '1'));

        $body = json_decode($res->getBody());
        Record::process($body->result, $apiVersion);

        $this->trackEvent('record', $request);

        return response()->json($body);
    }

    public function xisbn(Request $request, Http $http, $isbn)
    {
        $res = $http->request('GET', 'http://xisbn.worldcat.org/webservices/xid/isbn/' . $isbn, [
            'query' => [
                'method' => 'getMetadata',
                'format' => 'json',
                'fl' => '*',
            ]
        ]);

        $this->trackEvent('xisbn', $request);

        return response($res->getBody(), 200)
            ->header('Content-Type', 'application/json');
    }
}
