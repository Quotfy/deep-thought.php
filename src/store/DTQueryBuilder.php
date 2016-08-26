<?php
/**
 * DTQueryBuilder
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
 * @package    Deep-Thought
 * @author     Blake Anderson <blake@expressiveanalytics.com>
 * @copyright  2013-2016 Expressive Analytics, LLC <info@expressiveanalytics.com>
 * @license    http://choosealicense.com/licenses/mit
 * @link       http://www.expressiveanalytics.com/
 * @since      version 1.0.0
 */

class DTQueryBuilder{
	public $db = null;
	protected $from_clause = null;
	protected $join_clause = "";
	protected $group_by = "";
	protected $where_clause = "1=1";
	protected $having_clause = "";
	protected $enforce;
	protected $filter;
	protected $limit_clause = "";
	protected $order_by = "";
	protected $columns = array();

	function __construct($db){
		$this->db = $db;
	}

	/**
		@return returns itself for chaining
	*/
	function where($where_str){
		if($this->where_clause!="1=1")
			DTLog::warn("WHERE clause overridden! Did you mean to use enforce? '{$this->where_clause}' => '{$where_str}'");
		$this->where_clause = $where_str;
		return $this;
	}

	/** if +filter+ is set, it is appended to +where_clause+ using AND */
	public function buildWhereClause(){
		$col_esc = $this->db->col_esc;
		$wc = (!empty($this->enforce)?"({$this->enforce}) AND ":"")."({$this->where_clause})"; //ALWAYS wrap the where clause, or it conflicts with enforce (e.g. [enforcer] and "name like 'a' OR name like 'b'")
		if(isset($this->filter) && count($this->filter)>0){
			return $wc ." AND ". implode(" AND ",array_map(function($k,$v) use ($col_esc){
				if(!is_int($k))
					$k = "{$col_esc}{$k}{$col_esc}";
				if($v===null) //handle null-matching
					return "{$k} IS NULL";
				if(is_array($v)){ //in the case of an array, the elements are [op,exp,txfunc]
					if(is_array($v[1]))
						$val = "(".implode(",",array_map(function($v){return DTQueryBuilder::formatValue($v);},$v[1])).")";
					else if($v[1]=="NULL")
						$val = "NULL";
					else
						$val = DTQueryBuilder::formatValue($v[1]);
					if(isset($v[2])) //check for transform function
						$val = $v[2]."(".$val.")";
					return "{$k} {$v[0]} {$val}";
				}
				return "{$k}=".DTQueryBuilder::formatValue($v);
			},array_keys($this->filter),$this->filter));
		}
		return $wc;
	}

	/**
		if $params is null, returns the current filter
	*/
	public function filter(Array $params=null){
		if(!isset($params))
			return $this->filter;
		if(!isset($this->filter))
			$this->filter = array();
		$this->filter = array_merge($this->filter,$params);
		return $this;
	}

	/** this is a special override of filter() and where() to make sure that enforcers have the final say in the filtering */
	public function enforce($str){
		if(isset($this->enforce))
			DTLog::warn("ENFORCER overridden: '{$this->enforce}' => '{$str}'");
		$this->enforce = $str;
		return $this;
	}

	public function fail(){
		$this->filter = array("1"=>0); // 1==0 always fails
		return $this;
	}

	/** overrides columns from the select statement with '+v+ as +k+'
		@note values are not cleaned or processed before querying, use +formatValue()+ before calling this method with user input
	 */
	public function addColumns(Array $cols){
		if(count(array_filter(array_keys($cols),'is_string')))
			$cols = array_map(function($k,$v){ return "{$v} as {$k}"; }, array_keys($cols),$cols);
		$this->columns = array_merge($this->columns,$cols);
		return $this;
	}

	/**
		@return returns itself for chaining
	*/
	public function from($from_str=null){
		if(empty($from_str))
			return $this->from_clause;
		$this->from_clause = $from_str;
		return $this;
	}

	public function nestAs($cols="*",$alias){
		$qb = new DTQueryBuilder($this->db);
		$from = "(".$this->selectStatement($cols).") as {$alias}";
		$qb->from($from);
		return $qb;
	}

	public function limit($limit_count){
		$this->limit_clause = "LIMIT {$limit_count}";
		return $this;
	}

	/** @param table - this should be the (escaped) table name */
	public function join($table,$condition){
		$this->join_clause .= " JOIN {$table} ON ({$condition})";
		return $this;
	}

	public function leftJoin($table,$condition){
		$this->join_clause .= " LEFT JOIN {$table} ON ({$condition})";
		return $this;
	}

	public function groupBy($str){
		$this->group_by = "GROUP BY ".$str;
		return $this;
	}

	public function orderBy($str){
		$this->order_by = "ORDER BY ".$str;
		return $this;
	}

	public function having($str){
		$this->having_clause = "HAVING ({$str})";
		return $this;
	}

	public function select($cols="*"){
		$stmt = $this->selectStatement($cols);
		return $this->db->select($stmt);
	}

	public function selectKV($cols="*"){
		$stmt = $this->selectStatement($cols);
		return $this->db->selectKV($stmt);
	}

	public function selectStatement($cols="*"){
		$column_clause = $cols;
		$col_esc = $this->db->col_esc;
		if(count($this->columns)>0)
			$column_clause .= ", ".implode(",",array_map(function($c) use ($col_esc){return "{$col_esc}{$c}{$col_esc}";},$this->columns));
			//$column_clause .= ", ".implode(",",array_map(function($k,$v){return "{$v} as {$k}";},array_keys($this->columns),$this->columns));
		return "SELECT {$column_clause} FROM {$this->from_clause} {$this->join_clause} WHERE ".$this->buildWhereClause()." {$this->group_by} {$this->having_clause} {$this->order_by} {$this->limit_clause}";
	}

	public function count($cols="*"){
		//$restore = $this->columns;
		//$this->columns = array();
		$stmt = $this->nestAs($cols,"dt_countable")->selectStatement("COUNT(*) as count");
		$row = $this->db->select1($stmt);
		//$this->columns = $restore;
		return $row["count"];
	}

	public function sum($col){
		$row = $this->select1("SUM({$col}) as total");
		return $row["total"];
	}

	/**
		@return returns a single, matching row or null
	*/
	public function select1($cols="*"){
		$this->limit("1");
		$stmt = $this->selectStatement($cols);
		$rows = $this->db->select($stmt);
		if(count($rows)>0){
			return $rows[0];
		}
		return null;
	}

	public function selectAs($className,$cols="*"){
		$stmt = $this->selectStatement($cols);
		return $this->db->selectAs($stmt,$className);
	}

	public function update(array $properties){
		if(count($properties)>0){
			$col_esc = $this->db->col_esc;
			$set_str = implode(",",array_map(function($k,$v) use ($col_esc) {return "{$col_esc}{$k}{$col_esc}=".DTQueryBuilder::formatValue($v);},array_keys($properties),$properties));
			$stmt = "UPDATE {$this->from_clause} SET {$set_str} WHERE ".$this->buildWhereClause();
			$this->db->query($stmt);
			return true;
		}
		return false;
	}

	public function insert($properties){
		if(count($properties)>0){
			$col_esc = $this->db->col_esc;
			$cols_str = implode(",",array_map(function($c) use ($col_esc){return "{$col_esc}{$c}{$col_esc}";},array_keys($properties)));
			$vals_str = implode(",",array_map(function($v){return DTQueryBuilder::formatValue($v);},array_values($properties)));
			$stmt = "INSERT INTO {$this->from_clause} ({$cols_str}) VALUES ({$vals_str});";
			return $this->db->insert($stmt);
		}
		return $this->db->insertEmpty($this->from_clause);
	}

	public function delete(){
		try{
			$stmt = "DELETE FROM {$this->from_clause} WHERE ".$this->buildWhereClause();
			$this->db->query($stmt);
			return true;
		}catch(Exception $e){}
		return false;
	}

	public static function formatValue($v){
		if(!isset($v)||$v==="NULL")
			return "NULL";
		else if(is_array($v))
			return "'".json_encode($v)."'";
		else if(substr($v,0,11)=="\\DTSQLEXPR\\") //handle expressions as literals, as long as params are cleaned, this should never be possible from users because of the unescaped backslash
			return substr($v,11);
		return "'".(string)$v."'"; // ALWAYS quote other values to avoid 'id=1 OR 1=1' attacks
	}

	// this gives an idea of what the QB is all about... for debugging
	public function __toString(){
		return $this->selectStatement();
	}
}
