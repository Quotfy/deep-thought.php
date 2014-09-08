<?php namespace ExpressiveAnalytics\DeepThought;
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

use ExpressiveAnalytics\DeepThought\DTDatabase;

class DTSQLiteDatabase extends DTDatabase{
	public function connect($dsn){
		$parts = parse_url($dsn);
		$database = $parts["path"];
		$this->conn = new \SQLite3($database);
	}
	
	public function select($query){
		$object = array();
		if(($result=$this->conn->query($query))===false){
			DTLog::error($this->conn->lastErrorMsg()."\n".$query);
			return $object;
		}
		while($result!==false && $row=$result->fetchArray(SQLITE3_ASSOC)){
			$object[] = $row;
		}
		return $object;
	}
	
	public function query($query){
		if($this->conn->exec($query)===false)
			throw new Exception($this->conn->lastErrorMsg()."\n".$query);
	}
	
	public function clean($param){
		return $this->conn->escapeString($param);
	}
	
	public function disconnect(){
		$this->conn->close();
	}
	
	public function lastInsertID(){
		return $this->conn->lastInsertRowID();
	}
	
	public function insert($query){
		$this->query($query);
		return $this->lastInsertID();
	}
	
	public function placeholder(&$params,$val){
		$params[] = $val;
		$i = count($params);
		return ":{$i}";
	}
	
	public function prepare($name){
		$name = isset($name)?$name:"DT_prepared_".rand();
		return $this->conn->prepare($name);
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
		$object = array();
		while($result!==false && $row=$result->fetchArray(SQLITE3_ASSOC)){
			$object[] = $row;
		}
		return $object;
	}
	
	public function execute_insert($name,$params){
		$this->execute($name,$params);
		return $this->lastInsertID();
	}
	
	public function columnsForTable($table){
		return array_reduce($this->select("PRAGMA table_info(`{$table}`)"),
			function($rV,$cV) { $rV[]=$cV['name']; return $rV; },array());
	}
	
	public function allTables(){
		return array_reduce($this->select("SELECT name FROM sqlite_master WHERE type='table';"),
			function($row,$item) { if($item['name']!="sqlite_sequence") $row[] = $item['name']; return $row; },array());
	}
}