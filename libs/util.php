<?php
class Util {
	private $logFile;
	private $lastErrorMySql;
	private $requestPerHour;
	private $sleepTime;

	public function __construct($logFileName = null, $requestPerHour = 3600000) {
		if($logFileName) {
			$this->logFile = fopen("log/" . $logFileName, "w") or die("Unable to open file!");
		}
		$this->lastErrorMySql = null;
		$this->requestPerHour = $requestPerHour;
		$this->setSleepTime();
	}

	public function getLogFile() {
		return $this->logFile;
	}

	public function logNotSetVar($var = null, $varName = null) {
		return self::logNSV($var, $varName, $this->logFile);
	}

	public static function logNSV($var = null, $varName = null, $file = null) {
		if(!$var) {
			$txt = "\t\tIS NOT SET: " . gettype($var) . " " . $varName . "\n";
			self::logToFile($txt, $file);
			return false;
		}
		return true;
	}

	public static function logToFile($txt = null, $file = null, $echo = 1) {
		if($txt) {
			if($echo) {
				echo $txt;
			}
			fwrite($file, $txt);
		}
		flush();
	}

	public function logTxt($txt = null, $echo = 1){
		self::logToFile($txt, $this->logFile, $echo);
	}

	public function logMySqliError($connMySqli = null, $query = null) {
		$echo = 0;
		if($this->logNotSetVar($connMySqli, '$connMySqli')) {
			if(mysqli_error($connMySqli)) {
				$msgError = mysqli_errno($connMySqli) . " " . mysqli_error($connMySqli);
				$txt = "\t\t\t+MYSQL ERROR: " . $msgError . "\n";
				if($query) {
					$txt .= "\t\t\t" . $query . "\n";
					$txt .= "\t\t\t" . str_repeat("-", 50) . "\n";
				}
				//echo 'mysqli_errno($connMySqli) = ' . mysqli_errno($connMySqli) . '<br />';
				if($this->lastErrorMySql != mysqli_errno($connMySqli)) {
					$this->lastErrorMySql = mysqli_errno($connMySqli);
					$echo = 1;
				}
				$this->logTxt($txt, $echo);
				return mysqli_error($connMySqli);
			}
		}
		return 0;
	}

	public function stringCleaner($str = null, $caseNull = null, $connMySqli = null) {
		$str = $str ? htmlentities($str, null, "UTF-8") : $caseNull;

		if($connMySqli) {
			$str = mysqli_real_escape_string($connMySqli, $str);
		}

		return $str;
	}

	public static function arraySquares($latMin, $lonMin, $latMax, $lonMax, $areaM2) {
		global $util;

		$minSomaCoordenada = 0.000001; //aumenta a distancia em aproximadamente 117 metros
		$metrosCoordenada = 117;

		$somaCoordenada = ($areaM2/$metrosCoordenada)*$minSomaCoordenada;

		$squares = array();

		$latS = $latMin;
		$lonW = $lonMin;

		do {
			$lonE = $lonW + $somaCoordenada;
			do {
				$latN = $latS + $somaCoordenada;

				$square['latS'] = $latS;
				$square['lonW'] = $lonW;
				$square['latN'] = $latN;
				$square['lonE'] = $lonE;

				$square['sw'] = $latS . ',' . $lonW;
				$square['ne'] = $latN . ',' . $lonE;

				array_push($squares, $square);
				flush();
				/*teste area
			echo "<span style='top:".$latS."px; left:" . $lonW . "px; position: absolute;'>SW</span>";
			echo "<span style='top:".$latN."px; left:" . $lonE . "px; position: absolute;'>NE</span>";
			*/

				$latS = $latN;
			} while($latN < $latMax);

			$lonW = $lonE;
			$latS = $latMin;
		} while($lonE < $lonMax);

		$util->logTxt("COUNT SQUARES: " . count($squares) . "\n");

		return $squares;
	}

	public function getUrl($url) {
		do {
			$request = file_get_contents($url);
		} while(!$request);

		sleep($this->sleepTime);
		/*
		$request = file_get_contents($url);
		if($request) {
			sleep($this->sleepTime);
		}
		*/
		return $request;
	}
	
	private function setSleepTime() {
		$requestPerHour = $this->requestPerHour;
		if(is_numeric($requestPerHour)) {
			$this->sleepTime = ceil((60*60)/$requestPerHour);
		} else {
			$this->sleepTime = 0;
		}
		
		$this->logTxt("SLEEP TIME: " . $this->sleepTime . "\n");
	}
	
	public static function string_compare($str_a, $str_b) {
		if(strlen($str_a) > strlen($str_b)) {
			$aux = $str_a;
			$str_a = $str_b;
			$str_b = $aux;
		}
		$length = strlen($str_a);
		$length_b = strlen($str_b);

		$i = 0;
		$segmentcount = 0;
		$segmentsinfo = array();
		$segment = '';
		while ($i < $length) 
		{
			$char = substr($str_a, $i, 1);
			if (strpos($str_b, $char) !== FALSE) 
			{               
				$segment = $segment.$char;
				if (strpos($str_b, $segment) !== FALSE) 
				{
					$segmentpos_a = $i - strlen($segment) + 1;
					$segmentpos_b = strpos($str_b, $segment);
					$positiondiff = abs($segmentpos_a - $segmentpos_b);
					$posfactor = ($length - $positiondiff) / $length_b; // <-- ?
					$lengthfactor = strlen($segment)/$length;
					$segmentsinfo[$segmentcount] = array( 'segment' => $segment, 'score' => ($posfactor * $lengthfactor));
				} 
				else 
				{
					$segment = '';
					$i--;
					$segmentcount++;
				} 
			} 
			else 
			{
				$segment = '';
				$segmentcount++;
			}
			$i++;
		}   

		// PHP 5.3 lambda in array_map      
		$totalscore = array_sum(array_map(function($v) { return $v['score'];  }, $segmentsinfo));
		return $totalscore;     
	}
	
	public static function string_compare2($str_a, $str_b) {
		if(strlen($str_a) > strlen($str_b)) {
			$aux = $str_a;
			$str_a = $str_b;
			$str_b = $aux;
		}
		$semelhante = strstr($str_b, $str_a);
		$similarcount = strlen($semelhante); 
		
		$fator = $similarcount / (strlen($str_b));
		return $fator;
	}
	
	public static function limpaAcentos($string) {
		return preg_replace('/[`^~\'"]/', null, iconv('UTF-8', 'ASCII//TRANSLIT', $string ));
	}
	
	public static function normalizaString($string) {
		$string = html_entity_decode($string);
		$string = Util::limpaAcentos($string);
		$string = strtolower($string);
		return $string;
	}
}
?>