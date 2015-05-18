<?php
/**
 * DTPreparedQueryBuilder
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

class DTPreparedQueryBuilder extends DTQueryBuilder{
	public $stmt_name;
	protected static $prepared_statements = array();
	protected $prep_vals;
	
	function __construct($db,$stmt){
		$this->stmt_name = $stmt;
		parent::__construct($db);
	}
	
	/** if +filter+ is set, it is appended to +where_clause+ using AND */
	public function buildWhereClausePrepared(&$prep_vals=array()){
		$wc = (!empty($this->enforce)?"({$this->enforce}) AND ":"")."({$this->where_clause})"; //ALWAYS wrap the where clause, or it conflicts with enforce (e.g. [enforcer] and "name like 'a' OR name like 'b'")
		if(isset($this->filter) && count($this->filter)>0){
			return $wc ." AND ". implode(" AND ",array_map(function($k,$v) use (&$prep_vals){
				if($v===null) //handle null-matching
					return "{$k} IS NULL";
				if(is_array($v)){ //in the case of an array, the elements are [op,exp]
					if(is_array($v[1]) && count($v)>1)
						$val = "(".implode(",",array_map(function($v){return $this->db->placeholder($prep_vals,$v);},$v[1])).")";
					else if($v[1]=="NULL")
						$val = "NULL";
					else
						$val = $this->db->placeholder($prep_vals,$v[1]);
					if(isset($v[2])) //check for transform function
						throw new Exception("Transformation functions are not allow in prepared statements!");
					return "{$k} {$v[0]} {$val}";
				}
				return "{$k}=".$this->db->placeholder($prep_vals,$v);
			},array_keys($this->filter),$this->filter));
		}
		return $wc;
	}
	
	public function select($cols="*"){
		$stmt_name = $this->stmt_name;
		$this->prep_vals = array();
		$stmt_name .= "_select1"; //make sure we don't conflict with insert stmt name in upsert
		$stmt = $this->selectStatement($cols);
		if(!isset(static::$prepared_statements[$stmt_name]))
				static::$prepared_statements[$stmt_name] = $this->db->prepareStatement($stmt,$stmt_name);
		return $this->db->execute(static::$prepared_statements[$stmt_name],$this->prep_vals);
	}
	
	public function select1($cols="*"){
		$this->limit(1);
		$rows = $this->select($cols);
		if(count($rows)>0){
			return $rows[0];
		}
		return null;
	}
	
	public function selectStatement($cols="*"){
		$column_clause = $cols;
		$prep_vals = &$this->prep_vals;
		if(count($this->columns)>0)
			$column_clause .= ", ".implode(",",array_map(function($k,$v) use (&$prep_vals){return "{$v} as {$k}";},array_keys($this->columns),$this->columns));
		return "SELECT {$column_clause} FROM {$this->from_clause} {$this->join_clause} WHERE ".$this->buildWhereClausePrepared($prep_vals)." {$this->group_by} {$this->having_clause} {$this->order_by} {$this->limit_clause}";
	}
	
	public function update(array $properties){
		$stmt_name = $this->stmt_name;
		if(count($properties)>0){
			$prep_vals = array();
			$stmt_name .= "_update"; //make sure we don't conflict with insert stmt name in upsert
			$set_str = implode(",",array_map(function($k,$v) use (&$prep_vals){return "{$k}=".$this->db->placeholder($prep_vals,$v);},array_keys($properties),$properties));
			$stmt = "UPDATE {$this->from_clause} SET {$set_str} WHERE ".$this->buildWhereClausePrepared($prep_vals);
			if(!isset(static::$prepared_statements[$stmt_name]))
				static::$prepared_statements[$stmt_name] = $this->db->prepareStatement($stmt,$stmt_name);
			$this->db->execute(static::$prepared_statements[$stmt_name],$prep_vals);
			return true;
		}
		return false;
	}

	public function insert($properties){
		$stmt_name = $this->stmt_name;
		if(count($properties)>0){
			$prep_vals = array();
			$stmt_name .= "_insert"; //make sure we don't conflict with update stmt name in upsert
			$cols_str = implode(",",array_keys($properties));
			$vals_str = implode(",",array_map(function($v) use (&$prep_vals){return $this->db->placeholder($prep_vals,$v);},array_values($properties)));
			$stmt = "INSERT INTO {$this->from_clause} ({$cols_str}) VALUES ({$vals_str});";
			if(!isset(static::$prepared_statements[$stmt_name]))
				static::$prepared_statements[$stmt_name] = $this->db->prepareStatement($stmt,$stmt_name);
			$this->db->execute(static::$prepared_statements[$stmt_name],$prep_vals);
			return $this->db->lastInsertID();
		}
		return false;
	}
}