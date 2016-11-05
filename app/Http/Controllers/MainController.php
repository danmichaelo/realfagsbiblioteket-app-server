<?php

namespace App\Http\Controllers;

class MainController extends Controller
{
    public function status(Request $request)
    {
        $devicePlatform = $request->get('device_platform');
        $appVersion = $request->get('app_version');

        app('db')->insert('INSERT INTO status (device_platform, app_version) VALUES (?, ?)',
            [$devicePlatform, $appVersion]);

        return response()->json(['status' => 'ok']);
    }

    public function search(Request $request)
    {
        $devicePlatform = $request->get('device_platform');
        $appVersion = $request->get('app_version');
        $query = $request->get('query');

        app('db')->insert('INSERT INTO search (device_platform, app_version, query) VALUES (?, ?)',
            [$devicePlatform, $appVersion]);


        return response()->json($results);
    }
}
