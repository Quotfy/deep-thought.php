<?php
/**
 * DTPgSQLDatabase
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
 
class DTPgSQLDatabase extends DTStore{
	public $ilike = "ILIKE";

	public function connect($dsn){
		$parts = parse_url($dsn);
		$user = $parts["user"];
		$pass = $parts["pass"];
		$host = $parts["host"];
		$db = substr($parts["path"],1); //omit starting slash
		$this->conn = @pg_connect("host={$host} dbname={$db} user={$user} password={$pass}");
		if(!$this->conn)
			throw new \Exception(DTLog::colorize("Could not connect to PostgreSQL.","error")."\ndatabase: host={$host} dbname={$db} user={$user}");
		@pg_set_client_encoding($this->conn, "UTF8");
	}
	
	public function select($query){
		$result = @pg_query($this->conn,$query);
		if(!$result){
			DTLog::error("Query failed:".pg_last_error()."\n{$query}");
			return array();
		}
		$rows = @pg_fetch_all($result);
		if(!$rows){
			$rows = array();
		}
		return $rows;
	}
	
	public function query($query){
		if(@pg_query($this->conn,$query)===false)
			DTLog::error("Failed query: ".pg_last_error()."\n{$query}");
	}
	
	public function clean($param){
		return @pg_escape_string($this->conn,$param);
	}
	
	public function disconnect(){
		@pg_close($this->conn);
		$this->conn = null;
	}
	
	public function lastInsertID(){
		$query = "SELECT LASTVAL() as id";
		$rows = $this->select($query); //get the id
		if(count($rows)==0) return 0;
		return $rows[0]["id"];
	}
	
	public function insert($query){
		$this->query($query);
		return $this->lastInsertID();
	}
	
	public function placeholder(&$params,$val){
		$params[] = $val;
		$i = count($params);
		return "\${$i}";
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
		if(@pg_prepare($this->conn, $name, $stmt)===false)
			throw new \Exception(DTLog::colorize(pg_last_error(),"error"));
		return $name;
	}
	
	public function execute($stmt,$params=array()){
		$result = @pg_execute($this->conn,$stmt,$params);
		if($result===false)
			DTLog::error("Query failed:".pg_last_error()."\n{$stmt}");
		$rows = pg_fetch_all($result);
		if(!$rows){
			$rows = array();
		}
		return $rows;
	}
	
	public function columnsForTable($table){
		return array_reduce( $this->select("select column_name from information_schema.columns where table_name='{$table}'"),
			function($row,$item) { $row[]=$item['column_name']; return $row; },array());
	}
	
	public function typeForColumn($table_name,$column_name){
		$types = $this->typesForTable($table_name);
		if(isset($types[$column_name]))
			return $types[$column_name];
		return "text";
	}
	
	public function typesForTable($table_name){
		return array_reduce( $this->select("select column_name, data_type from information_schema.columns where table_name='{$table_name}'"),
			function($row,$item) { $row[$item['column_name']]=$item['data_type']; return $row; },array());
	}
	
	public function allTables(){
		return array_reduce($this->select("SELECT relname FROM pg_stat_user_tables ORDER BY relname"),
			function($row,$item) { $row[]=$item["relname"]; return $row; }, array());
	}
}
