<?php
/**
 * DTStore
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

abstract class DTStore{
	public $tables = array(); //internal storage for loaded data
	public $dsn=null;
	public $readonly;
	public $dbname;
	public $ilike = "LIKE"; //keyword for case-insensitive search
	public $col_esc = "\"";
	public $conn = null;
	
	/**
	 * __construct function.
	 * 
	 * @access public
	 * @param mixed $dsnOrTables (default: array()) either a Data Source Name (DSN) or an array of tables in storage format
	 * @param bool $readonly (default: false)
	 * @return void
	 */
	function __construct($dsnOrTables=array(),$readonly=false){
		$this->readonly = $readonly;
		if(is_array($dsnOrTables)) // we were given the represented store
			$this->tables = $dsnOrTables;
		else // create the store from the specified DSN
			$this->connect($dsnOrTables);
	}
	
	/**
	 * initialize a temporary store with the given SQL.
	 * 
	 * @access public
	 * @static
	 * @param string $init_sql (default: "")
	 * @return void
	 */
	public static function init($init_sql=""){
		$dsn = "file://".tempnam(sys_get_temp_dir(),"dt.store.");
		$store = new static($dsn);
		$store->connect($dsn);
		$store->query($init_sql);
		return $store;
	}
	
	/**
	 * sets the internal storage to the provided tables by reference.
	 * 
	 * @access public
	 * @param array &$tables
	 * @return void
	 */
	public function shareTables(array &$tables){
		$this->tables = $tables;
	}

	/**
	 * creates a new storage by duplicating +store+.
	 * 
	 * @access public
	 * @static
	 * @param DTStore $store
	 * @return void
	 */
	public static function copy(DTStore $store){
		return new static($store->tables);
	}
	
	/**
	 * creates a new storage sharing the internal tables of +store+.
	 * 
	 * @access public
	 * @static
	 * @param DTStore $store
	 * @return void
	 */
	public static function share(DTStore $store){
		$new = new static();
		$new->shareTables($store->tables);
		return $new;
	}
	
//! Connection methods
///@name Connection methods
///@{

	/**
	 * connects to a data store via data source name.
	 * 
	 * @access public
	 * @abstract
	 * @param string $dsn
	 * @return void
	 */
	abstract public function connect($dsn);

	/**
	 * disconnects from data store, saving any ongoing transactions.
	 * 
	 * @access public
	 * @abstract
	 * @return void
	 */
	abstract public function disconnect();

	/**
	 * pushes internal storage to permanent storage.
	 * 
	 * @access public
	 * @warn will not modify existing tables
	 * @return void
	 */
	public function pushTables(){
		$permanent_tables = $this->allTables();
		foreach($this->tables as $table=>$t){
			if(!in_array($table,$permanent_tables)){ //make sure we skip existing tables
				$insert_vals = array(); $all_cols = array(); $insert_cols = array();
				foreach($t as $row){
					$vals = array(); $cols = array();
					foreach($row as $c=>$v){
						$cols[] = $c;
						if(!is_array($v)) //do our best to clean what's going in
							$v = $this->clean($v);
						$vals[] = DTQueryBuilder::formatValue($v); //collect values
						if(!in_array($c,$all_cols))
							$all_cols[] = $c; //merge into all_cols (we could store type info)
					}
					$insert_vals[] = implode(",",$vals);
					$insert_cols[] = implode(",",$cols);
				}
				//  create the table (for now, all columns are text)
				$create_cols = implode(",",array_map(function($c){ return "{$c} text"; },$all_cols));
				$stmt = "CREATE TABLE \"{$table}\" ({$create_cols});";
				$this->query($stmt);
				//  insert all rows (can't use prepared, cause we don't know how many cols)
				foreach($t as $i=>$row){
					$stmt = "INSERT INTO \"{$table}\" ({$insert_cols[$i]}) VALUES ({$insert_vals[$i]});";
					$this->insert($stmt);
				}
			}
		}
	}
	
	/**
	 * pulls all tables to internal storage.
	 * 
	 * @access public
	 * @return returns false if internal storage is already set
	 */
	public function pullTables(){
		if(isset($this->tables)&&count($this->tables)>0)
			return false;
		//for each table in the database
		foreach($this->allTables() as $table){
			$stmt = "SELECT * FROM {$table}";
			$this->tables[$table] = $this->select($stmt);
		}
	}
///@}
	
	
//! Query methods
///@name Query methods
///@{

	/**
	 * makes a value safe for storage.
	 * 
	 * @access public
	 * @abstract
	 * @param string $val
	 * @retval string the cleaned value
	 */
	abstract public function clean($val);

	/**
	 * executes a given query without expecting a result.
	 * 
	 * @access public
	 * @abstract
	 * @param string $stmt
	 * @return void
	 */
	abstract public function query($stmt);
	
	/**
	 * execute the given select statement and return the results.
	 * 
	 * @access public
	 * @abstract
	 * @param string $stmt
	 * @retval array returns the results of a query
	 */
	abstract public function select($stmt);

	/**
	 * get the ID for the last inserted row.
	 * 
	 * @access public
	 * @abstract
	 * @retval int returns the id of the last row inserted
	 */
	abstract public function lastInsertID();
	
	/**
	 * get a list of columns for the given +$table_name+.
	 * 
	 * @access public
	 * @abstract
	 * @param string $table_name
	 * @retval array returns an array column names
	 */
	abstract public function columnsForTable($table_name);
	
	/**
	 * get a list of all the tables.
	 * 
	 * @access public
	 * @abstract
	 * @return array returns the names of all tables in storage
	 */
	abstract public function allTables();
	
	
	/**
	 * select a single row as a key-value array
	 * 
	 * @access public
	 * @param string $stmt
	 * @retval array returns a single object matching query or null for no results
	 */
	public function select1($stmt){
		$rows = $this->select($stmt);
		return (count($rows)>0?$rows[0]:null);
	}

	/**
	 * convert a set of rows to objects of type +class_name+
	 * 
	 * @access public
	 * @param string $stmt
	 * @param string $class_name
	 * @retval array returns an array of objects of type +class_name+
	 */
	public function selectAs($stmt,$class_name){
		$list = array();
		$rows = $this->select($stmt);
		foreach($rows as $r){
			$obj = $list[] = new $class_name($r);
			$obj->setStore($this); //keep track of where we came from
		}
		return $list;
	}

	/**
	 * pairs the first 2 columns (key,val) in an assoc array.
	 * 
	 * @access public
	 * @param mixed $stmt
	 * @retval array the key-value paired query results
	 */
	public function selectKV($stmt){
		$list = array();
		$rows = $this->select($stmt);
		if(count($rows)>0){
			$cols = array_keys($rows[0]);
			$key_col = $cols[0];
			$val_col = $cols[1];
			foreach($rows as $r){ //pair keys and values
				$list[$r[$key_col]] = $r[$val_col];
			}
		}
		return $list;
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
	
	public function insertEmpty($table){
		return $this->insert("INSERT INTO {$table} DEFAULT VALUES");
	}
	
	/**
	 * prepare a statement.
	 * 
	 * @access public
	 * @abtract
	 * @param string $query
	 * @param string $name (default: null) the name of the statement, or a randomly generated name
	 * @return mixed the name or object to pass to the first arguement of execute()
	 */
	abstract public function prepareStatement($query,&$name=null);
	
	/**
	 * create the relevant placeholder value for a prepared statement.
	 * 
	 * @access public
	 * @abstract
	 * @param array &$params
	 * @param mixed $val
	 * @return string the placeholder to use
	 */
	abstract public function placeholder(&$params,$val);
	
	/**
	 * execute a prepared statement.
	 * 
	 * @access public
	 * @abstract
	 * @param mixed $stmt
	 * @param array $params (default: array())
	 * @retval array the results of the statement
	 */
	abstract public function execute($stmt,$params=array());
///@}


//! Query Builder methods
///@name Query Builder methods
///@{

	/**
	 * create an empty query builder object to work with the current storage.
	 * 
	 * @access public
	 * @return DTQueryBuilder a query builder connected to the current storage
	 */
	public function qb(){
		return new DTQueryBuilder($this);
	}
	
	/**
	 * create a new query builder with the given where clause.
	 * 
	 * @access public
	 * @param mixed $where_str
	 * @retval DTQueryBuilder returns a DTQueryBuilder with the appropriate where clause
	 */
	public function where($where_str){
		return $this->filter()->where($where_str);
	}
	
	/**
	 * create a new query builder with the given filter parameters.
	 * 
	 * @access public
	 * @param Array $filter (default: array())
	 * @retval DTQueryBuilder returns a DTQueryBuilder with the appropriate filter
	 */
	public function filter(Array $filter=array()){
		$qb = $this->qb();
		return $qb->filter($filter);
	}
	
	/**
	 * create a query builder to represent a prepared statement.
	 * 
	 * @access public
	 * @param string $stmt_name a unique name for the prepared statement
	 * @retval DTPreparedQueryBuilder the prepared QB
	 */
	public function prepare($stmt_name=null){
		return new DTPreparedQueryBuilder($this,$stmt_name);
	}
///@}
	
//! Transaction methods
///@name Transaction methods
///@{
	
	/**
	 * initiates an atomic transaction.
	 * 
	 * @access public
	 * @return void
	 */
	public function begin(){
		$this->query("BEGIN");
	}
	
	/**
	 * saves the changes of the current transaction.
	 * 
	 * @access public
	 * @return void
	 */
	public function commit(){
		$this->query("COMMIT");
	}
	
	/**
	 * cancels current transaction and reverts to pre-transaction state.
	 * 
	 * @access public
	 * @return void
	 */
	public function rollback(){
		$this->query("ROLLBACK");		
	}
	
///@}

//! Date methods
///@name Date methods
///@{

	/**
	 * get the current UTC time in stroage format.
	 * 
	 * @access public
	 * @static
	 * @retval returns a storage-formatted string representing the current UTC timestamp
	 */
	public static function now(){
		return static::gmdate();
	}
	
	/**
	 * get the current date in storage format.
	 * 
	 * @access public
	 * @static
	 * @param mixed $timestamp (default: null)
	 * @retval string returns a storage-formatted string representing the date
	 */
	public static function day($timestamp=null){
		$timestamp = isset($timestamp)?$timestamp:time();
		return date("Y-m-d e",$timestamp);
	}
	
	/**
	 * get the current date for storage
	 * 
	 * @access public
	 * @static
	 * @param mixed $timestamp (default: null)
	 * @retval string returns a storage-formatted string (in local time) representing the given timestamp
	 */
	public static function date($timestamp=null){
		$timestamp = isset($timestamp)?$timestamp:time();
		return date("Y-m-d H:i:s e",$timestamp);
	}
	
	/**
	 * get the current date for storage as UTC
	 * 
	 * @access public
	 * @static
	 * @param mixed $timestamp (default: null)
	 * @retval string returns a storage-formatted string (in UTC) representing the given timestamp
	 */
	public static function gmdate($timestamp=null){
		$timestamp = isset($timestamp)?$timestamp:time();
		return gmdate("Y-m-d H:i:s e",$timestamp);
	}
	
	/**
	 * storage-format for local time.
	 * 
	 * @access public
	 * @static
	 * @param mixed $timestamp (default: null)
	 * @return void
	 */
	public static function time($timestamp=null){
		$timestamp = isset($timestamp)?$timestamp:time();
		return date("H:i:s",$timestamp);
	}
	
	/**
	 * localizedDate function.
	 * 
	 * @access public
	 * @static
	 * @param int $timestamp (default: null)
	 * @return void
	 */
	public static function localizedDate($timestamp=null){
		return strftime("%x",$timestamp);
	}
	
	/**
	 * returns the localized time string.
	 * 
	 * @access public
	 * @static
	 * @param int $timestamp (default: null)
	 * @return void
	 */
	public static function localizedTime($timestamp=null){
		return strftime("%X",$timestamp);
	}
///@}
	
	/**
	 * call disconnect() on destruct.
	 * 
	 * @access public
	 * @return void
	 */
	function __destruct() {
		try{
			$this->disconnect();
		}catch(\Exception $e){}
   }
}