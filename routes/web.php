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

$router->get('/realfagsbiblioteket-app/', function() {
	return redirect('status');
});
$router->get('/realfagsbiblioteket-app/status', 'MainController@status');
$router->get('/realfagsbiblioteket-app/search', 'MainController@search');
$router->get('/realfagsbiblioteket-app/groups/{id}', 'MainController@group');
$router->get('/realfagsbiblioteket-app/records/{id}', 'MainController@record');
$router->get('/realfagsbiblioteket-app/xisbn/{isbn}', 'MainController@xisbn');

