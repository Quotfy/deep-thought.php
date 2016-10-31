<?php
/**
 * DTSQLiteDatabase
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

class DTSQLiteDatabase extends DTStore{

	/**
	 * connects to an SQLite data store via data source name.
	 *
	 * @access public
	 * @abstract
	 * @param string $dsn
	 * @return void
	 */
	public function connect($dsn){
		$parts = parse_url($dsn);
		$database = $parts["path"];
		$this->conn = new \SQLite3($database);
		$this->conn->createFunction('LEVENSHTEIN', 'DTSQLiteDatabase::levenshtein', 2);
	}

	public static function levenshtein($a, $b){
		return levenshtein($a, $b);
	}

	/**
	 * execute the given select statement and return the results.
	 *
	 * @access public
	 * @param string $stmt
	 * @retval array returns the results of a query
	 */
	public function select($stmt){
		$object = array();
		if(($result=$this->conn->query($stmt))===false){
			DTLog::error(DTLog::colorize($this->conn->lastErrorMsg(),"error")."\n".$stmt);
			return $object;
		}
		while($result!==false && $row=$result->fetchArray(SQLITE3_ASSOC))
			$object[] = $row;
		return $object;
	}

	/**
	 * executes a given query without expecting a result.
	 *
	 * @access public
	 * @param string $stmt
	 * @return void
	 */
	public function query($stmt){
		if(@$this->conn->exec($stmt)===false)
			throw new \Exception(DTLog::colorize($this->conn->lastErrorMsg(),"error")."\n".$stmt);
	}

	/**
	 * makes a value safe for storage.
	 *
	 * @access public
	 * @param string $val
	 * @retval string the cleaned value
	 */
	public function clean($val){
		return $this->conn->escapeString($val);
	}

	/**
	 * disconnects from data store, saving any ongoing transactions.
	 *
	 * @access public
	 * @return void
	 */
	public function disconnect(){
		if(!isset($this->conn))
			throw new \Exception("Attempt to disconnect an nonexistent connection.");
		if(@$this->conn->close()===false)
			throw new \Exception(DTLog::colorize($this->conn->lastErrorMsg(),"error")."\n".$stmt);
	}

	/**
	 * get the ID for the last inserted row.
	 *
	 * @access public
	 * @retval int returns the id of the last row inserted or 0
	 */
	public function lastInsertID(){
		return $this->conn->lastInsertRowID();
	}

	/**
	 * execute an insert statement.
	 *
	 * @access public
	 * @param string $stmt
	 * @retval int the id of the new row
	 */
	public function insert($stmt){
		$this->query($stmt);
		return $this->lastInsertID();
	}

	/**
	 * create the relevant placeholder value for a prepared statement.
	 *
	 * @access public
	 * @param array &$params the mainfest for prepared values
	 * @param mixed $val the value to exchange for the placeholder
	 * @return string the placeholder to use
	 */
	public function placeholder(&$params,$val){
		$params[] = $val;
		$i = count($params);
		return ":{$i}";
	}

	/**
	 * create a query builder to represent a prepared statement.
	 *
	 * @access public
	 * @param string $stmt (optional) a unique name for the prepared statement
	 * @param string $name (default:null) the name of the statement, randomly generated if not supplied
	 * @retval a value appropriate for the first argument of execute()
	 */
	public function prepareStatement($stmt,&$name=null){
		$name = isset($name)?$name:"DT_prepared_".rand();
		return $this->conn->prepare($stmt);
	}

	public function execute($stmt,$params=array()){
		foreach($params as $i=>$p){
			$type = SQLITE3_TEXT;
			if(is_integer($p))
				$type = SQLITE3_INTEGER;
			else if(is_float($p))
				$type = SQLITE3_FLOAT;
			else if(is_null($p))
				$type = SQLITE3_NULL;
			$stmt->bindValue(":".($i+1),$p,$type);
		}
		$result = $stmt->execute();
		if($result===false)
			throw new \Exception(DTLog::colorize($this->conn->lastErrorMsg(),"error"));
		$rows = array();
		while($result!==false && $row=$result->fetchArray(SQLITE3_ASSOC)){
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * get the list of column names for a table.
	 *
	 * @access public
	 * @param string $table the name of the table
	 * @retval array the list of table columns
	 */
	protected $_storage_cols = array();
	public function columnsForTable($table){
		if(!isset($this->_storage_cols[$table]))
			$this->_storage_cols[$table] = array_reduce($this->select("PRAGMA table_info(`{$table}`)"),
				function($rV,$cV) { $rV[]=$cV['name']; return $rV; },array());
		return $this->_storage_cols[$table];
	}

	/**
	 * get a generic type for +$table_name+.+$column_name+.
	 *
	 * @access public
	 * @abstract
	 * @param string $table_name
	 * @param string $column_name
	 * @retval string returns the column type
	 */
	public function typeForColumn($table_name,$column_name){
		$types = $this->typesForTable($table_name);
		if(isset($types[$column_name]))
			return $types[$column_name];
		return "text";
	}

	public function typesForTable($table_name){
		return array_reduce($this->select("PRAGMA table_info(`{$table_name}`)"),
			function($rV,$cV) { $rV[$cV['name']]=$cV['type']; return $rV; },array());
	}

	/**
	 * get the list of all the tables.
	 *
	 * @access public
	 * @retval array the list of table names
	 */
	public function allTables(){
		return array_reduce($this->select("SELECT name FROM sqlite_master WHERE type='table';"),
			function($row,$item) { if($item['name']!="sqlite_sequence") $row[] = $item['name']; return $row; },array());
	}
}
