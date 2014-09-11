<?php
/**
 * DTMySQLDatabase
 *
 * Copyright (c) 2013-2014, Expressive Analytics, LLC <info@expressiveanalytics.com>.
 * All rights reserved.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @package    Deep Thought
 * @author     Blake Anderson <blake@expressiveanalytics.com>
 * @copyright  2013-2014 Expressive Analytics, LLC <info@expressiveanalytics.com>
 * @license    http://choosealicense.com/licenses/mit
 * @link       http://www.expressiveanalytics.com/
 * @since      version 1.0.0
 */

class DTMySQLDatabase extends DTStore{
	public function connect($dsn){
		$parts = parse_url($dsn);
		$user = $parts["user"];
		$pass = $parts["pass"];
		$host = $parts["host"];
		$this->dbname = $db = substr($parts["path"],1); //omit starting slash
		$this->conn = new \mysqli($host,$user,$pass,$db);
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
			@$this->conn->close();
		$this->conn = null;
	}
	
	public function query($query){
		if($this->conn==null)
			return;
		$result = $this->conn->query($query);
		if(!$result)
			DTLog::error("Failed query: ".$this->conn->error."\n{$query}");
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
	
	/**
	 * prepare a statement.
	 * 
	 * @access public
	 * @param string $query
	 * @param string $name (default: null) the name of the statement, or a randomly generated name
	 * @return mixed the name or object to pass to the first arguement of execute()
	 */
	public function prepareStatement($stmt,&$name=null){
		$name = isset($name)?$name:"DT_prepared_".rand();
		$result = $this->conn->prepare($stmt);
		if($result===false)
			throw new \Exception(DTLog::colorize($this->conn->error,"error"));
		return $result;
	}
	
	public function execute($stmt,$params=array()){
		foreach($params as $p){
			if($stmt->bind_param("s",$p)===false)
				throw new \Exception(DTLog::colorize($this->conn->error,"error"));
		}
		$object = array();
		if(@$stmt->execute()===false)
			throw new \Exception(DTLog::colorize($this->conn->error,"error"));
		$result = $stmt->get_result();
		$rows = array();
        while ($row = $result->fetch_assoc()){
        	$rows[] = $row;
        }
        return $rows;
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
