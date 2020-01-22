<?php

use Symfony\Component\HttpFoundation\Request;

use helena\classes\App;
use helena\classes\Session;
use helena\services\admin as services;
use helena\services\backoffice as backofficeServices;
use minga\framework\Params;
use helena\services\backoffice\publish\CacheManager;
use helena\entities\backoffice as entities;

// Admins


// ********************************* Servicios *********************************

// ******* Administración *********************************


App::GetOrPost('/services/admin/UpdateUser', function (Request $request) {
	if ($app = Session::CheckIsMegaUser())
		return $app;
	$user = App::ReconnectJsonParamMandatory(entities\User::class, 'u');
	$password = Params::Get('p');
	$verification = Params::Get('v');

	$controller = new services\UserService();
	$ret = $controller->UpdateUser($user, $password, $verification);
	return App::Json($ret);
});

App::Get('/services/admin/GetWorks', function (Request $request) {
	if ($app = Session::CheckIsSiteReader())
		return $app;
	$controller = new backofficeServices\WorkService();
	$filter = Params::GetMandatory('f');
	$timeFilter = Params::GetInt('t', 0);
	$ret = $controller->GetWorksByType($filter, $timeFilter);
	return App::Json($ret);
});

App::Get('/services/admin/GetUsers', function (Request $request) {
	if ($app = Session::CheckIsMegaUser())
		return $app;
	$controller = new services\UserService();
	$ret = $controller->GetUsers();
	return App::Json($ret);
});

App::Get('/services/admin/LoginAs', function (Request $request) {
	if ($app = Session::CheckIsMegaUser())
		return $app;
	$userId = Params::GetIntMandatory('u');
	$controller = new services\UserService();
	$ret = $controller->LoginAs($userId);
	return App::Json($ret);
});

App::Get('/services/admin/DeleteUser', function (Request $request) {
	if ($app = Session::CheckIsMegaUser())
		return $app;

	$userId = Params::GetIntMandatory('u');
	$controller = new services\UserService();
	$ret = $controller->DeleteUser($userId);
	return App::Json($ret);
});


App::Get('/services/admin/UpdateWorkIndexing', function (Request $request) {
	if ($app = Session::CheckIsMegaUser())
		return $app;

	$workId = Params::GetIntMandatory('w');
	$value = Params::GetBoolMandatory('v');
	$controller = new services\WorkService();
	$ret = $controller->UpdateWorkIndexing($workId, $value);
	return App::Json($ret);
});


App::Get('/services/admin/services/admin/ClearMetadataPdfCache', function (Request $request) {
	if ($app = Session::CheckIsSiteReader())
		return $app;
	$controller = new CacheManager();
	$metadataId = Params::GetMandatory('m');
	$ret = $controller->CleanPdfMetadata($metadataId);
	return App::Json($ret);
});