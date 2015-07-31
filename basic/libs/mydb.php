<?php
class MyDB {
	private $host;
	private $user;
	private $password;
	private $db;
	private $util;
	
	public function __construct($host, $user, $password, $db, $util) {
		$this->host 	= $host;
		$this->user		= $user;
		$this->password	= $password;
		$this->db		= $db;
		$this->util		= $util;
	}
	
	public function query($query) {

		$mysql = mysqli_connect($this->host, $this->user, $this->password, $this->db);
		$queryResult		= null;
		$noError			= null;
		$msgError 			= null;
		$insertId 			= null;
		if($mysql && $query) {
			
			$queryResult		= mysqli_query($mysql, $query);
			$noError			= mysqli_errno($mysql);
			$msgError			= mysqli_error($mysql);
			$insertId			= mysqli_insert_id($mysql);
			if($queryResult instanceof mysqli_result) {
				$queryResult = mysqli_fetch_all($queryResult, MYSQLI_ASSOC);
			}
			if($this->util) {
				$this->util->logMySqliError($mysql, $query);
			}
		}
		mysqli_close($mysql);
		
		$return = new stdClass();
		$return->queryResult	= $queryResult;
		$return->noError		= $noError;
		$return->msgError		= $msgError;
		$return->insertId		= $insertId;
		
		return $return;
	}

	public function stringCleaner($str = null, $caseNull = null) {
		if($this->util) {
			$mysql = mysqli_connect($this->host, $this->user, $this->password, $this->db);
			$str = $this->util->stringCleaner($str, $caseNull, $mysql);
			mysqli_close($mysql);
			
			return $str;
		}
		return $str;
	}
}
?>