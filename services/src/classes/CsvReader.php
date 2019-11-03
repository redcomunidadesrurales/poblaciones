<?php

namespace helena\classes;

use minga\framework\ErrorException;

use minga\framework\Str;

/**
 * Lee archivos csv de a partes.
 * Autodetecta encoding y separador
 * de texto. También puede setearse
 * luego del constructor:
 * $csv->textQualifier = '"';
 * $csv->delimiter = ',';
 * $csv->encoding = 'UTF-8';
 *
 * También detecta demitador, para ello hay
 * que setearlo como null en el contructor.
 * $csv = new CsvReader(null);
 *
 * Devuelve arrays de MAX_ROWS líneas, a menos que
 * se pase por parámetro un valor distinto.
 * Los arrays pueden estar ordenados por filas o
 * columnas (GetNextRows, GetNextRowsByColumn).
 *
 * Detecta separador decimal si se le pasa un array
 * con datos $csv->DetectDecimalSeparator().
 *
 * Trata de mantener el array bidimencional
 * cuadrado, al menos de la cantidad de
 * campos que tiene el header (si se leyó).
 *
 * Ejemplo de uso:
 *
 * $csv = new CsvReader();
 * // abre el archivo.
 * $csv->Open('file.csv');
 *
 * // obtiene el header.
 * $headerLine = $csv->GetHeader();

 * $data = [];
 * // mientras el archivo tenga datos.
 * while($csv->eof == false)
 * {
 *    // obtiene el texto por columnas.
 *    $part = $csv->GetNextRowsByColumn();
 *    for($i = 0; $i < count($part); $i++)
 *    {
 *     // mergea los datos traidos de las partes
 *   	 if(isset($data[$i]) == false)
 *   		 $data[$i] = [];
 *   	 $data[$i] = array_merge($data[$i], $part[$i]);
 *    }
 * }
 * // cierra el archivo.
 * $csv->Close();
 *
 */
class CsvReader
{
	//Este valor depende de la memoria disponible
	//a menos memoria bajar el varor. Aprox. 5000 = 128mb
	const MAX_ROWS = 5000;

	const MAX_BYTES_ENCODING = 100000;

	private $headLength = 0;
	private $handle = false;
	private $start = 0;

	public $eof = false;
	public $delimiter = null;
	public $textQualifier = '"';
	public $decimalSeparator = null;
	public $encoding = null;

	public function __construct($textQualifier = '"',
	  	$delimiter = null, $encoding = null)
	{
		$this->encoding = $encoding;
		$this->delimiter = $delimiter;
		$this->textQualifier = $textQualifier;
	}

	public function Open($filename)
	{
		if($this->handle !== false)
			$this->InternalReset();

		$this->handle = fopen($filename, 'r');

		if ($this->handle === false)
		{
			$this->InternalReset();
			throw new ErrorException('Could not open file');
		}

		$this->DetectEncoding();
	}

	private function InternalReset()
	{
		$this->Close();
		$this->start = 0;
		$this->headLength = 0;

		$this->eof = false;
		$this->delimiter = null;
		$this->textQualifier = '"';
		$this->decimalSeparator = null;
		$this->encoding = null;
	}

	public function GetHeader()
	{
		$header = $this->ReadHeaderLine();

		if($this->delimiter === null)
			$this->AutodetectDelimiter($header);

		if($this->textQualifier === null)
			$this->AutodetectTextQualifier($header);

		$ret = $this->HeaderToArray($header);
		$this->headLength = count($ret);
		return $ret;
	}

	public function GetNextRowsByColumn($count = self::MAX_ROWS)
	{
		return $this->GetNextRows($count, true);
	}

	public function GetNextRowsByRow($count = self::MAX_ROWS)
	{
		return $this->GetNextRows($count, false);
	}

	private function GetNextRows($count, $byCol)
	{
		if($count <= 0)
			return [];

		$res = [];
		if($this->delimiter === null)
		{
			$res = $this->ReadLines(1);
			$count--;
			$this->AutodetectDelimiter($res[0]);
		}

		// Puede que no tenga qualifier el header pero sí la primera fila
		if($this->textQualifier === null)
		{
			if(count($res) == 0)
			{
				$res = array_merge($res, $this->ReadLines(1));
				$count--;
			}
			$this->AutodetectTextQualifier($res[0]);
		}

		$res = array_merge($res, $this->ReadLines($count));

		if($byCol)
			return $this->LinesToArrayByColumn($res);
		else
			return $this->LinesToArrayByRow($res);
	}

	private function AutodetectDelimiter($line)
	{
		$countComma = substr_count($line, ',');
		$countColon = substr_count($line, ';');
		$countTab = substr_count($line, "\t");

		$max = max($countComma, $countColon, $countTab);

		if ($max == 0
			|| ($countComma == $countColon && $countComma == $max)
			|| ($countColon == $countTab && $countColon == $max)
			|| ($countComma  == $countTab && $countComma == $max))
			throw new ErrorException('Could not detect delimiter');

		if($max == $countComma)
			$this->delimiter = ',';
		else if($max == $countColon)
			$this->delimiter = ';';
		else if($max == $countTab)
			$this->delimiter = "\t";
	}

	private function AutodetectTextQualifier($header)
	{
		if($this->delimiter === null)
			throw new ErrorException('Set delimiter first');

		$double = substr_count($header, '"' . $this->delimiter . '"');
		$single = substr_count($header, "'" . $this->delimiter . "'");

		//default
		$this->textQualifier = '"';
		if(($single == 0 && $double == 0
			&& $header[0] == "'" && Str::EndsWith($header, "'"))
			|| ($double < $single))
			$this->textQualifier = "'";
	}

	public function DetectDecimalSeparator(array $rows)
	{
		$cant = min(count($rows), 500);

		$point = 0;
		$comma = 0;
		for($i = 0; $i < $cant; $i++)
		{
			for($j = 0; $j < count($rows[$i]); $j++)
			{
				$val = str_replace(',', '.', $rows[$i][$j]);
				if(Str::IsNumber($val))
				{
					if(Str::Contains($rows[$i][$j], ','))
						$comma++;
					else if(Str::Contains($rows[$i][$j], '.'))
						$point++;
				}
			}
		}

		//Ver si redondear ajustar
		//mínimos y máximos, etc...
		if($point > $comma)
			return '.';
		else if($point < $comma)
			return ',';
		else
			return '';
	}

	public function Close()
	{
		if($this->handle !== false)
			fclose($this->handle);

		$this->handle = false;
	}

	private function ReadHeaderLine()
	{
		if($this->handle === false)
			throw new ErrorException('Open file first');

		if($this->start > 0)
			throw new ErrorException('Header already read');

		$res = $this->ReadLines(1);
		return $res[0];
	}

	private function HeaderToArray($line)
	{
		return $this->LinesToArray([$line], true, true);
	}

	private function LinesToArrayByRow(array $lines)
	{
		return $this->LinesToArray($lines, false);
	}

	private function LinesToArrayByColumn(array $lines)
	{
		return $this->LinesToArray($lines, true);
	}

	private function LinesToArray(array $lines, $byCol, $isHeader = false)
	{
		if($this->delimiter === null)
			throw new ErrorException('Must set delimiter or call AutodetectDelimiter');

		$data = [];

		for($i = 0; $i < count($lines); $i++)
		{
			$len = strlen($lines[$i]);
			$start = 0;
			$posDelim = 0;
			$line = [];

			while($start < $len)
			{
				$posQuote = strpos($lines[$i], $this->textQualifier, $start);
				if($posQuote === $start)
				{
					$newStart = $posQuote + 1;
					//busca comilla de cierre
					while(true)
					{
						$posEndQuote = strpos($lines[$i], $this->textQualifier, $newStart);
						if($posEndQuote === false) //no hay comilla de cierre
						{
							$posDelim = $len;
							$line[] = substr($lines[$i], $start, $posDelim - $start);
							break;
						}
						elseif($posEndQuote === $len - 1 || $lines[$i][$posEndQuote + 1] !== $this->textQualifier) //encontró
						{
							$line[] = str_replace($this->textQualifier . $this->textQualifier, $this->textQualifier,
								substr($lines[$i], $posQuote + 1, $posEndQuote - $posQuote - 1));
							$posDelim = $posEndQuote + 1;
							break;
						}
						else //encuentra comilla doble, sigue buscando
							$newStart = $posEndQuote + 2;
					}
				}
				else // sin comillas
				{
					$posDelim = strpos($lines[$i], $this->delimiter, $start);
					if($posDelim === false)
						$posDelim = $len;
					$line[] = substr($lines[$i], $start, $posDelim - $start);
				}
				$start = $posDelim + 1;
			}

			if($isHeader)
				return $line;

			//hace que las filas tengan al menos la mismas cantidad de elementos que el header.
			if(count($line) < $this->headLength)
				$line = array_merge($line, array_fill(0, $this->headLength - count($line), null));

			if($byCol)
			{
				for($j = 0; $j < count($line); $j++)
					$data[$j][] = $line[$j];
			}
			else
				$data[] = $line;
		}
		return $data;
	}

	private function ReadLines($count)
	{
		if($this->eof)
			throw new ErrorException('End of file');

		$lines = [];
		for($i = $this->start; $i < $this->start + $count; $i++)
		{
			$line = fgets($this->handle);

			if($line !== false)
			{
				if($this->encoding !== null && $this->encoding != 'UTF-8')
					$line = Str::Convert($line, "UTF-8", $this->encoding);
				$line = trim($line);
				if ($line !== '')
					$lines[] = $line;
			}
			if($line === false || feof($this->handle) !== false)
			{
				$this->eof = true;
				$this->Close();
				break;
			}
		}
		$this->start += $count;
		return $lines;
	}

	private function DetectEncoding()
	{
		if($this->encoding !== null)
			return;

		$prev = ftell($this->handle);

		while(feof($this->handle) == false)
		{
			$str = fread($this->handle, self::MAX_BYTES_ENCODING);
			if($str === false)
				break;

			$this->encoding = Str::DetectEncoding($str);
			if($this->encoding == 'UTF-8')
				break;
		}
		fseek($this->handle, $prev);
	}

}

