<?php

namespace helena\db\frontend;

use minga\framework\QueryPart;
use minga\framework\Profiling;
use minga\framework\ErrorException;
use minga\framework\Str;

class SpatialConditions
{
	private $preffix;

	public function __construct($preffix)
	{
		$this->preffix = $preffix;
	}

	public function CreateRegionQuery($clippingRegionId, $levelId = null)
	{
		$from = "snapshot_clipping_region_item_geography_item ";

		$where = $this->preffix . "_geography_item_id = cgv_geography_item_id AND "
			. " cgv_clipping_region_item_id = ? ";

		$params = array($clippingRegionId);

		if ($levelId != null)
		{
			$where .= " AND cgv_geography_id = ? ";
			$params[] = $levelId;
		}

		return new QueryPart($from, $where, $params);
	}

	public function CreateCircleQuery($circle, $effectiveDatasetType)
	{
		$from = "";
		$params = array();

		$envelope = $circle->GetEnvelope();
		$where = $this->EnvelopePart($envelope);

		$where .=  $this->CircleCondition($circle, $effectiveDatasetType);

		return new QueryPart($from, $where, $params);
	}

	public function CreateFeatureQuery($featureId)
	{
		$from = "";
		$params = array($featureId);
		$where = $this->preffix . "_feature_id = ?";

		return new QueryPart($from, $where, $params);
	}
	private function EnvelopePart($envelope)
	{
		return "MBRIntersects(" . $this->preffix . "_envelope, PolygonFromText('" . $envelope->ToWKT() . "'))";
	}
	public function CreateSimpleEnvelopeQuery($envelope)
	{
		$from = "";
		$where = $this->EnvelopePart($envelope);
		$select = "";
		$params = array();

		return new QueryPart($from, $where, $params, $select);
	}

	public function CreateEnvelopeQuery($envelope)
	{
		$from = "";
		$where = $this->EnvelopePart($envelope);
		$select = "MBRContains(PolygonFromText('" . $envelope->ToWKT() . "'), " . $this->preffix . "_envelope) as Inside";
		$params = array();

		return new QueryPart($from, $where, $params, $select);
	}

	public function GetGeometry($datasetType, $zoom)
	{
		$params = "";
		$rZoom = (int) (($zoom + 2) / 3);
		if ($zoom > 10 || $rZoom > 5) $rZoom = 5;

		if ($datasetType == 'L')
		{
			// Si es un metric de puntos, eval�a la ubicaci�n del punto
			$from		= "";
			$where	= "";
			$select = $this->preffix . "_location as value";
		}
		else if ($datasetType == 'S')
		{
			// Si es un metric de formas, eval�a la ubicaci�n del shape
			$from		= "";
			$where	= "";
			$select = "geometry_r" . $rZoom . " as value";
		}
		else if ($datasetType == 'D')
		{
			$from		= "snapshot_geography_item";
			$where	= "giw_geography_item_id = " . $this->preffix . "_geography_item_id ";
			$select = "giw_geometry_r" . $rZoom . " as value";
		}
		else
			throw new ErrorException("Invalid datasetType " . $datasetType);

		return new QueryPart($from, $where, $params, $select);
	}

	public function CircleCondition($circle, $effectiveDatasetType)
	{
		if ($effectiveDatasetType == 'L')
		{
			// Si es un metric de puntos, eval�a la ubicaci�n del punto
			$sql = " AND EllipseContains(". $circle->Center->ToMysqlPoint() . ", " .
				$circle->RadiusToMysqlPoint() . ", " . $this->preffix . "_location)";
		}
		else if ($effectiveDatasetType == 'S')
		{
			// Si es un metric de formas, eval�a la ubicaci�n del shape
			$sql = " AND EXISTS (SELECT 1 FROM snapshot_shape_dataset_item WHERE sdi_feature_id = miv_feature_id " .
				" AND EllipseContainsGeometry(". $circle->Center->ToMysqlPoint() . ", " .
				$circle->RadiusToMysqlPoint() . ", sdi_geometry_r3))";
			/*$sql = " AND EllipseContains(". $circle->Center->ToMysqlPoint() . ", " .
				$circle->RadiusToMysqlPoint() . ", miv_location)";*/
		}
		else if ($effectiveDatasetType == 'D')
		{
			// Si es un metric de datos, eval�a la ubicaci�n del geography
			$sql = " AND EXISTS (SELECT 1 FROM snapshot_geography_item WHERE giw_geography_item_id = " . $this->preffix . "_geography_item_id " .
				" AND EllipseContainsGeometry(". $circle->Center->ToMysqlPoint() . ", " .
				$circle->RadiusToMysqlPoint() . ", giw_geometry_r3))";
		}
		else
			throw new ErrorException("Invalid datasetType " + $effectiveDatasetType);
		return $sql;
	}

	public function UrbanityCondition($urbanity)
	{
		$field = $this->preffix . "_urbanity";
		if (strlen($urbanity) > 4) throw new ErrorException('Valor inv�lido para ' . $urbanity);
		if ($urbanity === null) return '';
		$sql = $field . " IN ('N'";
		foreach(['U', 'D', 'R', 'L'] as $validFilter)
		if (Str::Contains($urbanity, $validFilter))
			$sql .= ",'" . $validFilter . "'";

		return ' AND ' . $sql . ') ';
	}
}


