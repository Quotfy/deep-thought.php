<?php
/**
 * DTParams
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
 * @package    Deep Thought (HTTP)
 * @author     Blake Anderson <blake@expressiveanalytics.com>
 * @copyright  2013-2014 Expressive Analytics, LLC <info@expressiveanalytics.com>
 * @license    http://choosealicense.com/licenses/mit
 * @link       http://www.expressiveanalytics.com/
 * @since      version 1.0.0
 */

class DTParams extends DTModel{
	protected $params;
	public $db;

	function __construct(array $params=null,$db=null){
		$this->params = isset($params)?$params:$_REQUEST;
		$this->db = isset($db)?$db:DTSettingsStorage::defaultStore();
	}
	
//======================
//! Parameter Handling
//======================
/** @name Parameter Parsing
 *  Methods for parsing parameters into distinct types
 */
///@{
	public function param($name,$default=null){
		return isset($this->params[$name])?$this->params[$name]:$default;
	}

	public function jsonParam($name,$default=null){
		return json_decode($this->param($name,$default),true);
	}
	
	public function intParam($name,$default=null){
		return intval($this->param($name,$default));
	}
	
	public function doubleParam($name,$default=null){
		return doubleval($this->param($name,$default));
	}
	
	public function boolParam($name,$default=null){
		return static::parseBool($this->param($name,$default));
	}
	
	public function dateParam($name,$default=null){
		$val = $this->param($name,$default);
		return ($val=="")?null:$this->db->date(strtotime($val));
	}
	
	public function timeParam($name,$default=null){
		return $this->db->time(strtotime($this->param($name,$default)));
	}
	
	public function checkboxParam($name){
		return isset($this->params[$name])?1:0;
	}
	
	public function phoneParam($name,$default=null){
		if(preg_match("/(\d{3})?[^\d]*(\d{3})[^\d]*(\d{4})/",$this->param($name,$default),$matches)){
			$area_code = ($matches[1]=="")?"":"({$matches[1]}) ";
			return "{$area_code}{$matches[2]}-{$matches[3]}";
		}
		return "";
	}
	
	public function arrayParam($name,$default=null){
		return static::parseArray($this->param($name,$default),$this->db);
	}
	
	/** @return returns a string param, cleaning it if +db+ is valid */
	public function stringParam($name,$default=null){
		return static::parseString($this->param($name,$default),$this->db);
	}
	
	/** @return returns all parameters, using db cleaning */
	public function allParams(array $defaults=array()){
		$params = $defaults;
		foreach($this->params as $k=>$v){
			if(is_null($v))
				$params[$k] = null;
			else if(is_array($v))
				$params[$k] = static::parseArray($v,$this->db);
			else if($v instanceof SimpleXMLElement)
				$params[$k] = $v; //pass XML children straight through
			else
				$params[$k] = static::parseString($v,$this->db);				
		}
		return $params;
	}
	
//====================
//! Parse Methods
//====================
	public static function parseBool($val){
		if(is_bool($val)) return $val;
		if(is_string($val))
			$val = trim(strtolower($val));
		else
			return $val==true;
		switch($val){
			case "true":
			case "t":
			case "yes":
			case "y":
			case "on":
			case "1":
				return true;
			case "false":
			case "f":
			case "no":
			case "n":
			case "off":
			case "0":
				return false;
		}
		return null;
	}

	public static function parseArray($val,$db){
		$arr = $val;
		if(!is_array($arr)){ //if this isn't array, assume it is json encoded or single value
			if(empty($arr)) return array();
			$arr = json_decode($arr,true);
			if(!isset($arr)) $arr = $val;
			if(!is_array($arr)) //must have been a single value
				$arr = array($arr);
		}
		$out = array();
		foreach($arr as $k=>$v) //clean all the array params
			if($v==null)
				$out[$k] = "NULL";
			else if(is_array($v))
				$out[$k] = static::parseArray($v,$db); //recursively parse inner arrays
			else if($v instanceof SimpleXMLElement)
				$out[$k] = $v; //pass XML children straight through
			else
				$out[$k] = static::parseString($v,$db);
		return $out;
	}
	
	public static function parseString($val,$db){
		return isset($db)?$db->clean($val):$val;
	}
}