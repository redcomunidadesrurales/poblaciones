<?php

namespace helena\classes;

use minga\framework\Params;
use minga\framework\MessageBox;
use minga\framework\PhpSession;
use helena\caches\WorkPermissionsCache;
use minga\framework\Profiling;
use helena\services\frontend\SelectedMetricService;
use helena\services\backoffice\publish\PublishDataTables;

class Session
{
	public static function GetCurrentUser()
	{
		$account = Account::Current();
		return $account;
	}

	public static function IsAuthenticated()
	{
		$account = Account::Current();
		return $account->IsEmpty() == false;
	}

	public static function IsMegaUser()
	{
		$account = Account::Current();
		return $account->IsMegaUser();
	}
	public static function IsSiteEditor()
	{
		$account = Account::Current();
		return $account->IsSiteEditor();
	}
	public static function IsSiteReader()
	{
		$account = Account::Current();
		return $account->IsSiteReader();
	}

	public static function CheckIsWorkPublicOrAccessible($workId)
	{
		Profiling::BeginTimer();
		// Se fija los permisos
		if (self::IsWorkPublicOrAccessible($workId))
			$ret = null;
		else
		{
			if ($app = Session::CheckSessionAlive())
				$ret = $app;
			else
				$ret = self::NotEnoughPermissions();
		}
		Profiling::EndTimer();
		return $ret;
	}

	public static function CheckIsWorkPublicOrAccessibleByDataset($datasetId)
	{
		Profiling::BeginTimer();
		// Se fija los permisos
		if (self::IsWorkPublicOrAccessibleByDataset($datasetId))
			$ret = null;
		else
		{
			if ($app = Session::CheckSessionAlive())
				$ret = $app;
			else
				$ret = self::NotEnoughPermissions();
		}		Profiling::EndTimer();
		return $ret;
	}

	public static function CheckIsWorkPublicOrAccessibleByMetricVersion($metricId, $metricVersionId)
	{
		Profiling::BeginTimer();
		// Se fija los permisos
		if (self::IsWorkPublicOrAccessibleByMetricVersion($metricId, $metricVersionId))
			$ret = null;
		else
		{
			if ($app = Session::CheckSessionAlive())
				$ret = $app;
			else
				$ret = self::NotEnoughPermissions();
		}
		Profiling::EndTimer();
		return $ret;
	}

	public static function IsWorkPublicOrAccessible($workId)
	{
		$private = App::Db()->fetchScalarIntNullable("SELECT wrk_is_private FROM work WHERE wrk_id = ? LIMIT 1", array($workId));
		if (!$private)
			return true;
		if (!Session::IsAuthenticated())
			return false;
		else
			return self::IsWorkReaderShardified($workId);
	}

	public static function IsWorkPublicOrAccessibleByDataset($datasetId)
	{
		$res = App::Db()->fetchAssoc("SELECT wrk_is_private, wrk_id FROM dataset JOIN work ON dat_work_id = wrk_id WHERE dat_id = ? LIMIT 1", array($datasetId));
		if ($res === null || !$res['wrk_is_private'])
			return true;
		if (!Session::IsAuthenticated())
			return false;
		else
			return self::IsWorkReaderShardified($res['wrk_id']);
	}

	public static function IsWorkPublicOrAccessibleByMetricVersion($metricId, $metricVersionId)
	{
		$selectedMetricService = new SelectedMetricService();
		$metric = $selectedMetricService->GetSelectedMetric($metricId);
		if ($metric === null) return true;
		foreach($metric->Versions as $version)
			if ($version->Version->Id === $metricVersionId)
			{
				if ($version->Version->WorkIsPrivate)
				{
					if (!Session::IsAuthenticated())
						return false;
					else
						return self::IsWorkReaderShardified($version->Version->WorkId);
				}
				else
					return true;
			}
		return true;
	}



	public static function IsWorkEditor($workId)
	{
		$permission = WorkPermissionsCache::GetCurrentUserPermission($workId);
		if ($permission === WorkPermissionsCache::ADMIN ||
				$permission === WorkPermissionsCache::EDIT)
				return true;
		$account = Account::Current();
		return $account->IsSiteEditor();
	}
	public static function IsWorkReaderShardified($workShardifiedId)
	{
		$workId = PublishDataTables::Unshardify($workShardifiedId);
		return self::IsWorkReader($workId);
	}
	public static function IsWorkReader($workId)
	{
		$permission = WorkPermissionsCache::GetCurrentUserPermission($workId);
		if ($permission === WorkPermissionsCache::ADMIN ||
				$permission === WorkPermissionsCache::EDIT ||
				$permission === WorkPermissionsCache::VIEW)
				return true;
		$account = Account::Current();
		return $account->IsSiteReader();
	}

	public static function GoProfile()
	{
		return App::Redirect(Links::GetBackofficeUrl());
	}
	public static function CheckIsMegaUser()
	{
		if ($app = Session::CheckSessionAlive())
		{
			return $app;
		}
		// Se fija los permisos
		if (self::IsMegaUser())
			return null;
		else
			return self::NotEnoughPermissions();
	}
	private static function MustLogin()
	{
		$account = Account::Current();
		$url = App::RedirectLoginUrl();
		http_response_code(403);
		MessageBox::ThrowMessage("Para acceder a esta opción debe ingresar con su cuenta de usuario.
				<br><br>Seleccione continuar para identificarse.", $url);
	}
	public static function NotEnoughPermissions()
	{
		$account = Account::Current();
		$url = App::RedirectLoginUrl();
		http_response_code(401);
		MessageBox::ThrowMessage("El usuario ingresado ('" . $account->user . "') no dispone de suficientes permisos para acceder a esta opción
				<br><br>Seleccione continuar para identificarse con otra cuenta.", $url);
	}
	private static function ElementNotFound()
	{
		MessageBox::ThrowMessage("El elemento indicado no ha sido encontrado.");
	}
	public static function CheckIsWorkEditor($workId)
	{
		Profiling::BeginTimer();
		if ($app = Session::CheckSessionAlive())
			$ret = $app;
		// Se fija los permisos
		else if (self::IsWorkEditor($workId))
			$ret = null;
		else
			$ret = self::NotEnoughPermissions();
		Profiling::EndTimer();
		return $ret;
	}

	public static function CheckIsDatasetEditor($datasetId)
	{
		Profiling::BeginTimer();
		if ($app = Session::CheckSessionAlive())
			$ret = $app;
		else
		{
			$workId = self::GetDatasetWorkId($datasetId);
			if ($workId === null)
				$ret = self::ElementNotFound();
			// Se fija los permisos
			else if (self::IsWorkEditor($workId))
				$ret = null;
			else
				$ret = self::NotEnoughPermissions();
		}
		Profiling::EndTimer();
		return $ret;
	}

	public static function GetDatasetWorkId($datasetId)
	{
		Profiling::BeginTimer();
		$workId = App::Db()->fetchScalarIntNullable("SELECT dat_work_id FROM draft_dataset WHERE dat_id = ?", array($datasetId));
		Profiling::EndTimer();
		return $workId;
	}
	public static function CheckIsDatasetReader($datasetId)
	{
		Profiling::BeginTimer();
		if ($app = Session::CheckSessionAlive())
			$ret = $app;
		else {
			$workId = self::GetDatasetWorkId($datasetId);
			if ($workId === null)
				$ret = self::ElementNotFound();
			// Se fija los permisos
			else if (self::IsWorkReader($workId))
				$ret = null;
			else
				$ret = self::NotEnoughPermissions();
		}
		Profiling::EndTimer();
		return $ret;
	}
	public static function CheckIsWorkReader($workId)
	{
		Profiling::BeginTimer();
		if ($app = Session::CheckSessionAlive())
			$ret = $app;
		// Se fija los permisos
		else if (self::IsWorkReader($workId))
			$ret = null;
		else
			$ret = self::NotEnoughPermissions();
		Profiling::EndTimer();
		return $ret;
	}
	public static function CheckIsSiteEditor()
	{
		Profiling::BeginTimer();
		if ($app = Session::CheckSessionAlive())
			$ret = $app;
		// Se fija los permisos
		else if (self::IsSiteEditor())
			$ret = null;
		else
			$ret = self::NotEnoughPermissions();
		Profiling::EndTimer();
		return $ret;
	}

	public static function CheckIsSiteReader()
	{
		Profiling::BeginTimer();
		if ($app = Session::CheckSessionAlive())
			$ret = $app;
		// Se fija los permisos
		else if (self::IsSiteReader())
			$ret = null;
		else
			$ret = self::NotEnoughPermissions();
		Profiling::EndTimer();
		return $ret;
	}
	public static function CheckSessionAlive()
	{
		if (!Session::IsAuthenticated())
		{
			return self::MustLogin();
		}
		else
			return null;
	}

	public static function Logoff()
	{
		if (!Session::IsAuthenticated())
			return;
		$account = Account::Current();
		$masterUser = Account::GetMasterUser();
		if ($masterUser != '')
		{
			Account::RevertImpersonate();
		}
		else
		{
			Remember::Remove($account);
			Account::ClearCurrent();
			PhpSession::Destroy();
		}
		// Reinicia si había un administrador por detrás
	}


}

