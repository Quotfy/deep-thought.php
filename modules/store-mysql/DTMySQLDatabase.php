<?php
/**
	An abstract interface using object-oriented MySQLi
*/

class DTMySQLDatabase extends DTDatabase{
	public function connect($dsn){
		$parts = parse_url($dsn);
		$user = $parts["user"];
		$pass = $parts["pass"];
		$host = $parts["host"];
		$this->dbname = $db = substr($parts["path"],1); //omit starting slash
		$this->conn = new mysqli($host,$user,$pass,$db);
		if (mysqli_connect_errno()){
			unset($this->conn);
			DTLog::error("Connect failed: ".mysqli_connect_error());
		}
	}
	
	public function select($query){
		$result = $this->conn->query($query);
		if(!$result)
			DTLog::error("Query failed: ".$this->conn->error."\n".$query);
		$rows = array();
		while($row = $result->fetch_assoc()){
			$rows[] = $row;
		}
		return $rows;
	}
	
	public function clean($param){
		return @mysqli_escape_string($this->conn,$param);
	}
	
	public function disconnect(){
		if(isset($this->conn))
			$this->conn->close();
	}
	
	public function query($query){
		$result = $this->conn->query($query);
		if(!$result)
			throw new Exception("Query failed: ".$this->conn->error."\n".$query);
	}
	
	public function insert($query){
		$this->query($query);
		return $this->lastInsertID();
	}
	
	public function lastInsertID(){
		$query = "SELECT LAST_INSERT_ID() as id";
		$rows = $this->select($query);
		return $rows[0]["id"];
	}
	
	public function placeholder(&$params,$val){
		$params[] = $val;
		return "?";
	}
	
	public function prepare($query){
		return $this->conn->prepare($query);
	}
	
	public function execute($stmt,$params=array(),$fmt="s"){
		$stmt->bind_param($fmt,$params);
		$object = array();
		$stmt->execute();
		$result = $stmt->get_result();
		$rows = array();
        while ($row = $result->fetch_assoc()){
        	$rows[] = $row;
        }
        return $rows;
	}
	
	public function execute_insert($name,$params){
		$this->execute($name,$params);
		return $this->lastInsertID();
	}
	
	public function columnsForTable($table){
		return array_reduce( $this->select("SHOW columns FROM {$table}"),
			function($rV,$cV) { $rV[]=$cV['Field']; return $rV; },array());
		return null;
	}
	
	public function allTables(){ 
		return array_reduce($this->select("SELECT table_name FROM information_schema.tables WHERE table_schema='{$this->dbname}'"), //this pulls ALL the tables (all databases)
			function($row,$item) { $row[]=$item['table_name']; return $row; },array());
	}
	
	
	/*
		These need to be overridden, because MySQL sucks at timezones
	*/
	public static function day($timestamp=null){
		$timestamp = isset($timestamp)?$timestamp:time();
		return date("Y-m-d",$timestamp);
	}
	
	/** @return returns a storage-formatted string representing the given timestamp */
	public static function date($timestamp=null){
		$timestamp = isset($timestamp)?$timestamp:time();
		return date("Y-m-d H:i:s",$timestamp);
	}
	
	/** @return returns a storage-formatted string representing the given (local) timestamp convered to UTC */
	public static function gmdate($timestamp=null){
		$timestamp = isset($timestamp)?$timestamp:time();
		return gmdate("Y-m-d H:i:s",$timestamp);
	}
}
