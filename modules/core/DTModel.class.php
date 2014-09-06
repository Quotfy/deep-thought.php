<?php
/**
 * DTModel
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
 
namespace ExpressiveAnalytics\DeepThought;
 
class DTModel implements arrayaccess {
	/** require properties to be defined in the class, defaults to false */
	protected static $strict_properties = false;
	protected static $storage_table = null;
	protected static $primary_key_column = "id";
	
	protected $db=null;
	public $id = 0;

    protected $_properties = array(); /** @internal */
    protected $_bypass_accessors = false; /** @internal a flag used to bypass accessors during construction */
    
    /**
    	@param paramsOrQuery - an assoc. array of default properties or DTQueryBuilder object
    */
    function __construct($paramsOrQuery=null){
   		if(!isset($paramsOrQuery)) return; //just create an empty object
    	$this->_bypass_accessors = true; //we want direct access to properties by default
		if(is_array($paramsOrQuery)){
			$properties = $paramsOrQuery;
    	}else if($paramsOrQuery instanceof DTQueryBuilder){ //grab the parameters from storage
    		$this->db=$paramsOrQuery->db; //save where we came from
    		if(isset(static::$storage_table))
	    		$properties = $paramsOrQuery->from(static::$storage_table." ".get_called_class())->select1();
	    	if(!isset($properties))
    			throw new Exception("Failed to find ".get_called_class()." in storage.",1);
    	}
    	if(!isset($properties)){
    		DTLog::error("Invalid parameters used to construct DTModel (".json_encode($paramsOrQuery).")");
    		throw new Exception("Invalid parameters for DTModel constructor.");
    	}
		if(is_array($properties) && (count($properties)==0 || count(array_filter(array_keys($properties),'is_string')))) // must be an associative array
			foreach($properties as $k=>$v)
				$this[$k] = $v;//make sure we go through the set method
		else
			DTLog::warn("Attempt to instantiate ".get_called_class()." from invalid type (".json_encode($properties).")",1);
			
		$this->_bypass_accessors = false; //make sure we use the accessors now
	}
    
    /**
    	looks for an accessor method (called set+offset+), or uses a basic storage mechanism
    	@return returns the value that was stored
    */
    public function offsetSet($offset, $value) {
    	if (is_null($offset)) {
            $this->_properties[] = $value;
            return $value;
        } else {
	    	$accessor = "set".preg_replace('/[^A-Z^a-z^0-9]+/','',$offset);
			if(!$this->_bypass_accessors && method_exists($this, $accessor)) //use the accessor method
				return $this->$accessor($value);
			else if(property_exists($this, $offset)){ //use the property
				$this->$offset = $value;
				return $value;
			}
			else if(static::$strict_properties==false){ // set object property
				$this->_properties[$offset] = $value;
				return $value;
			}
			/*else //it is not an error to fail to set a property
				DTLog::debug("failed to set property ({$offset})");*/
        }
    }
    public function offsetExists($offset) {
        return isset($this->_properties[$offset]);
    }
    public function offsetUnset($offset) {
        unset($this->_properties[$offset]);
    }
    /**
    	looks for an accessor method (called +offset+), or uses a basic storage mechanism
    */
    public function offsetGet($offset) {
    	$accessor = preg_replace('/[^A-Z^a-z^0-9]+/','',$offset);
		if(method_exists($this, $accessor)) //use the accessor method
			return $this->$accessor();
		else if(property_exists($this, $offset)) //use the property
			return $this->$offset;
		else if(static::$strict_properties==false)// get object property
			return isset($this->_properties[$offset])?$this->_properties[$offset]:null;
			
		DTLog::warn("property does not exist ({$offset})"); //this can happen for valid reasons with strict properties
		return null;
    }
    
    /**
    	override this method if you want to compare objects by a subset of properties
    	@return returns true if object is equal to +obj+
    */
    public function isEqual(DTModel $obj){
	    return $this==$obj;
    }
    
    /** attempts to access +property+ directly (does not work with accessors), assigning value of +f+ if not found */
    protected function selfOr(&$property,callable $f){
		return $property =
		isset($property)
		? $property
		: call_user_func($f);
	}
    
//==================
//! Storage Methods
//==================
    
    /**
    	override this method to customize the properties that get stored
    	@return returns an array of key-value pairs that can be used for storage
    	@note values should be properly formatted for storage (including quotes)
    */
    public function publicProperties(array $defaults=array(),$purpose=null){
		$public_params = array();
		$ref = new ReflectionClass($this);
		$publics = $ref->getProperties(ReflectionProperty::IS_PUBLIC);
		foreach($publics as $p){
			$k = $p->getName();
			$public_params[$k] = DTResponse::objectAsRenderable($this[$k]); //recursively get renderables
		}
		return array_merge($public_params,$defaults);
	}
	
	public function storageProperties(DTDatabase $db,array $defaults=array(),$purpose=null){
		$storage_params = array();
		$cols = $db->columnsForTable(static::$storage_table);
		if(count($cols)==0)
			DTLog::error("Found 0 columns for table (".static::$storage_table.")");
		foreach($cols as $k){
			if($purpose!="insert"||$k!=static::$primary_key_column) //don't try to insert the id, assume it's autoincrementing
				$storage_params[$k] = $this[$k];
		}
		return array_merge($defaults,$storage_params);
	}
	
	/**
		cleans properties in preparation for storage
	*/
	public function clean(DTDatabase $db=null){
		$db = isset($db)?$db:$this->db;
		$p = new DTParams($this->storageProperties($db,array(),"reinsertion"));
		$clean = $p->allParams();
		$this->merge($clean);
	}
	
	/** attempts to set each property as defined in +params+ (but never merges id property)
		@param the parameters to merge in
		@param changes - on return, this is a description of the changes to storage
		@return returns the number of properties updated to new values
	*/
	public function merge(array $params, &$changes=null){
		if($changes==null)
			$changes = array("old"=>array(),"new"=>array());
		$cols = $this->db->columnsForTable(static::$storage_table);
		$updated = 0;
		foreach($params as $k=>$v){
			if($k!="id" && ($this[$k]!=$v || $this[$k]=="")){
				if(in_array($k, $cols)){ //ignore properties that don't affect storage
					$changes["old"][$k] = $this[$k];
					$changes["new"][$k] = $v;
				}
				$this[$k] = $v;
				$updated++;
			}
		}
		return $updated;
	}
	
	/**
		convenience method for basic inserts based on storageProperties()
		@return returns the inserted id, or false if nothing was inserted
	*/
	public function insert(DTDatabase $db=null,DTQueryBuilder $qb=null){
		$db = (isset($db)?$db:$this->db);
		$this->setStore($db);
		$qb = (isset($qb)?$qb:new DTQueryBuilder($db)); //allow the query builder to be passed, in case it's a subclass
		$new_id = $qb->from(static::$storage_table)->insert($this->storageProperties($db,array(),"insert"));
		$this[static::$primary_key_column] = $new_id;
		return $new_id;
	}
	
	/**
		convenience method for basic updates based on storageProperties()
		@note uses the object's id property for where-clause, unless query-builder is passed
	*/
	public function update(DTDatabase $db=null,$qb=null){
		$db = (isset($db)?$db:$this->db);
		$qb = isset($qb)?$qb:$db->filter(array(static::$primary_key_column=>$this[static::$primary_key_column]));
		$properties = $this->storageProperties($db,array(),"update");
		return $qb->from(static::$storage_table)->update($properties);
	}
	
	/**
		delete the object in storage
		@param qb - this determines what is matched for delete, defaults to primary-key column
	*/
	public function delete(DTDatabase $db=null,$qb=null){
		$db = (isset($db)?$db:$this->db);
		$qb = isset($qb)?$qb:$db->where(static::$primary_key_column."='".$this[static::$primary_key_column]."'");
		return $qb->from(static::$storage_table)->delete();
	}
	
	/** convenience method for updating or inserting a record (as necessary)
		@param qb - a querybuilder to identify the record for updating
		@param params - the parameters to update/insert
		@param defaults - additional parameters for insert
		@param changes - on return, this contains a description of the modifications (old/new values)
		@return returns the updated or inserted object
	*/
	public static function upsert(DTQueryBuilder $qb,array $params,array $defaults=array(), &$changes=null){
		try{
			$obj = new static($qb); //if we fail out here, it's probably because the record needs to be inserted
			if(count($params)==0)
				return $obj; // there are no changes, let's get outta here
			$obj->clean($qb->db); //replace storage with clean varieties
			$obj->merge($params,$changes); // now we're ready to merge in the new stuff
			$obj->update($qb->db,$qb->filter(array(static::$primary_key_column=>$obj[static::$primary_key_column]))); //it's essential that this use the +primary_key_column+
			return $obj;
		}catch(Exception $e){
			if($e->getCode()==1){ //the record doesn't exist, insert it instead
				$obj = new static();
				$obj->setStore($qb->db);
				$obj->merge($defaults); //use the accessor for defaults
				$obj->merge($params,$changes);
				$obj->insert($qb->db);
				return $obj;
			}else
				throw $e;
		}
	}
	
	public static function select(DTQueryBuilder $qb,$cols="*"){
		return $qb->from(static::$storage_table." ".get_called_class())->selectAs(get_called_class(),$cols);
	}
	
	public static function count(DTQueryBuilder $qb){
		return $qb->from(static::$storage_table." ".get_called_class())->count();
	}
	
	public static function sum(DTQueryBuilder $qb,$col){
		return $qb->from(static::$storage_table." ".get_called_class())->sum($col);
	}
	
	public static function updateRows(DTQueryBuilder $qb,$params){
		return $qb->from(static::$storage_table)->update($params);
	}
	
	public static function deleteRows(DTQueryBuilder $qb){
		return $qb->from(static::$storage_table." ".get_called_class())->delete();
	}
	
	public static function byID($db,$id,$cols="*"){
		if(!($db instanceof DTStore))
			throw new Exception("invalid storage for id ('{$id}')");
		$rows = static::select($db->where(get_called_class().".".static::$primary_key_column."='{$id}'"),$cols);
		if(count($rows)>0)
			return $rows[0];
		return null;
	}
	
	function setStore($db){
		$this->db = $db;
	}
	
//! Property manipulation methods
///@name Property manipulation methods
///@{

	/** Two-way encryption method (use decode() to reverse the encoding)
		- from the PHP mcrypt docs (http://docs.php.net/manual/en/function.mcrypt-encrypt.php) */
	public static function encode($str,$salt){
	    $key = pack('H*', $salt);
	
	    # create a random IV to use with CBC encoding
	    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
	    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
	    
	    # creates a cipher text compatible with AES (Rijndael block size = 128) to keep the text confidential 
	    # only suitable for encoded input that never ends with value 00h (because of default zero padding)
	    $ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key,$str, MCRYPT_MODE_CBC, $iv);
	    $ciphertext = $iv . $ciphertext; # prepend the IV for it to be available for decryption
	    $ciphertext_base64 = base64_encode($ciphertext); # encode the resulting cipher text so it can be represented by a string
	    return $ciphertext_base64;
	}
	
	/** Reverse two-way encryption method (use encode() to create string)
		- from the PHP mcrypt docs (http://docs.php.net/manual/en/function.mcrypt-encrypt.php) */
	public static function decode($str,$salt){
		if(empty($str))
			return "";
		$key = pack('H*', $salt);
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
		$ciphertext_dec = base64_decode($str);
	    $iv_dec = substr($ciphertext_dec, 0, $iv_size);
	    $ciphertext_dec = substr($ciphertext_dec, $iv_size); // retrieves the cipher text (everything except the $iv_size in the front)
	    $plaintext_dec = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $ciphertext_dec, MCRYPT_MODE_CBC, $iv_dec); //may remove 00h valued characters from end of plain text
	    return rtrim($plaintext_dec,"\0");
	}
	
	// from http://stackoverflow.com/questions/2690504/php-producing-relative-date-time-from-timestamps
	public static function relativeTime($ts){
		if(!ctype_digit($ts))
	        $ts = strtotime($ts);

	    $diff = time() - $ts;
	    if($diff == 0)
	        return 'now';
	    elseif($diff > 0){
	        $day_diff = floor($diff / 86400);
	        if($day_diff == 0){
	            if($diff < 60) return 'just now';
	            if($diff < 120) return '1 minute ago';
	            if($diff < 3600) return floor($diff / 60) . ' minutes ago';
	            if($diff < 7200) return '1 hour ago';
	            if($diff < 86400) return floor($diff / 3600) . ' hours ago';
	        }
	        if($day_diff == 1) return 'Yesterday';
	        if($day_diff < 7) return $day_diff . ' days ago';
	        if($day_diff < 31) return ceil($day_diff / 7) . ' weeks ago';
	        if($day_diff < 60) return 'last month';
	        return date('F Y', $ts);
	    }else{
	        $diff = abs($diff);
	        $day_diff = floor($diff / 86400);
	        if($day_diff == 0){
	            if($diff < 120) return 'in a minute';
	            if($diff < 3600) return 'in ' . floor($diff / 60) . ' minutes';
	            if($diff < 7200) return 'in an hour';
	            if($diff < 86400) return 'in ' . floor($diff / 3600) . ' hours';
	        }
	        if($day_diff == 1) return 'Tomorrow';
	        if($day_diff < 4) return date('l', $ts);
	        if($day_diff < 7 + (7 - date('w'))) return 'next week';
	        if(ceil($day_diff / 7) < 4) return 'in ' . ceil($day_diff / 7) . ' weeks';
	        if(date('n', $ts) == date('n') + 1) return 'next month';
	        return date('F Y', $ts);
	    }
	}
	
	public function dateMDY($ts){
		return isset($ts)?date("m/d/Y",strtotime($ts)):"";
	}
		
///@}

}
