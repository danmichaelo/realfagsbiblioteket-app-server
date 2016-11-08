<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$app->get('/realfagsbiblioteket-app/', function() {
	return redirect()->action('MainController@status');
});
$app->get('/realfagsbiblioteket-app/status', 'MainController@status');
$app->get('/realfagsbiblioteket-app/search', 'MainController@search');
$app->get('/realfagsbiblioteket-app/groups/{id}', 'MainController@group');
$app->get('/realfagsbiblioteket-app/records/{id}', 'MainController@record');
