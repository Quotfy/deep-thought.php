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

class DTModel implements arrayaccess {
	/** require properties to be defined in the class, defaults to false */
	protected static $strict_properties = false;
	protected static $storage_table = null;
	protected static $primary_key_column = "id";

	protected static $has_a_manifest = array();
	protected static $has_many_manifest = array();
	protected static $is_a_manifest = array();

	protected $db=null;
	protected $input=array();
	public $id = 0;

	protected $dt_recursion_depth=null;

    protected $_properties = array(); /** @internal */
    protected $_bypass_accessors = false; /** @internal a flag used to bypass accessors during construction */

    protected $_unsanitary = true;

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
    		if(isset(static::$storage_table)){
    			$paramsOrQuery = static::selectQB($paramsOrQuery);
	    		$properties = $paramsOrQuery->select1("*, ".get_called_class().".*");
	    	}
	    	if(!isset($properties))
    			throw new Exception("Failed to find ".get_called_class()." in storage.\n".$paramsOrQuery,1);
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
			if(!$this->_bypass_accessors){
				if($offset!="many" && $offset!="a" && method_exists($this, $accessor)) //use the accessor method
					return $this->$accessor($value);
				//	note: setMany causes immediate database insertion
				//	this is necessary, because we need these objects hooked up
				$manifest = static::hasManyManifest();
				if(isset($manifest[$offset])) //this is a set-many relationship
					$value = $this->setMany($manifest[$offset],$value);
				$manifest = static::hasAManifest();
				if(isset($manifest[$offset])) //this is a has-a relationship
					$value = $this->setA($offset,$value);
			}
			if(property_exists($this, $offset)) //use the property
				return $this->$offset = $value;
			if(static::$strict_properties==false) // set object property
				return $this->_properties[$offset] = $value;
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
		if(property_exists($this, $offset)){ //use the property, if set
			$val = $this->$offset;
			if($val!==null)
				return $val;
		}
		if(static::$strict_properties==false){ // get object property, if set
			$val = isset($this->_properties[$offset])?$this->_properties[$offset]:null;
			if($val!==null)
				return $val;
		}
		$manifest = static::hasManyManifest();
		if(isset($manifest[$offset])){
			if(!isset($this->dt_recursion_depth)){ // we are at the top of the chain
				$last_idx = count($manifest[$offset])-1;
				if(is_integer($manifest[$offset][$last_idx])) // use the specified depth
					$this->dt_recursion_depth = $manifest[$offset][$last_idx];
				else
					$this->dt_recursion_depth = count($manifest[$offset]); //default recursion depth is the length of the chain
			}
			if($this->dt_recursion_depth>0){
				$val = $this->getMany($manifest[$offset]);
				foreach($val as $v) // decrement our recursion counter
					$v->dt_recursion_depth = $this->dt_recursion_depth-1;
				if(property_exists($this, $offset))
					$this->$offset = $val;
				else
					$this->_properties[$offset] = $val;
				return $val;
			}
		}
		$manifest = static::hasAManifest();
		if(isset($manifest[$offset])){
			if(!isset($this->dt_recursion_depth)) // we are at the top of the chain
				if(isset($manifest[$offset][2])) // use the specified depth
					$this->dt_recursion_depth = $manifest[$offset][2];
				else
					$this->dt_recursion_depth = count($manifest[$offset]); //default recursion depth is the length of the chain
			if($this->dt_recursion_depth>0){ // don't let us go wild here
				$val = $this->getA($manifest[$offset][0],$manifest[$offset][1]);
				if(isset($val))
					$val->dt_recursion_depth = $this->dt_recursion_depth-1;
				if(property_exists($this, $offset))
					$this->$offset = $val;
				else
					$this->_properties[$offset] = $val;
				return $val;
			}
		}
		return null;
    }

    public function isDirty($offset){
		if(in_array($offset,array_keys(get_class_vars(get_called_class()))) || in_array($offset,array_keys($this->_properties)))
			return true;
		return false;
	}

    /**
	    @param chain a chain of models to traverse
	    @param qb optional DTQueryBuilder (use <Model>_<seq#> for filtering)
	  @return returns an array of DTModels by traversing an entry in the has-many manifest
	*/
    public function getMany($chainOrName,DTQueryBuilder $qb=null){
	    $chain = $chainOrName;
	    if(!is_array($chainOrName)){
		    $manifest = $this->hasManyManifest();
		    $chain = $manifest[$chainOrName];
		}
	    if(!isset($qb))
		    $qb = $this->db->qb();

	    $qb = $this->getManyQB($chain,$qb,$target_class);
	    return $target_class::select($qb,"{$target_class}.*");
	}

	public function getManyQB($chainOrName,DTQueryBuilder $qb=null, &$target_class=null){
		$chain = $chainOrName;
	    if(!is_array($chainOrName)){
		    $manifest = $this->hasManyManifest();
		    $chain = $manifest[$chainOrName];
		}
		if(is_integer($chain[count($chain)-1])) // take the recursion limit off
			array_pop($chain);
    if(!isset($qb))
	    $qb = $this->db->qb();

		$link = explode(".",$chain[0]);
	    $key_col = $link[0]::columnForModel(get_called_class());
	    if(count($link)>1)
	    	$key_col = $link[1];
	    $key_val = $this[static::$primary_key_column];
	    $link = explode(".",array_pop($chain));
	    $target_class = $link[0];

	    $last_col = null;
	    if(count($chain)>0)
		    $last_col = $target_class::columnForModel($chain[count($chain)-1]);
	    if(count($link)>1)
	    	$last_col = $link[1];
	    $last_model = $last_alias = $target_class;
	    if(count($chain)>0) //if we are joining anything, we need to use group-by
	    	$qb->groupBy("{$target_class}.{$target_class::$primary_key_column}");
	    while(count($chain)>0){
		    $link = explode(".",array_pop($chain));
		    $model = $link[0];
		    $col = $model::columnForModel($last_model);
		    if(count($link)>1)
		    	$col = $link[1];

			$model_alias = $model."_".count($chain);
			if(($owner_alias = $model::aliasForParent($col))==null)
				$owner_alias = $model_alias;
			$qb->join("{$model::$storage_table} {$model_alias}","{$last_alias}.{$last_col}={$owner_alias}.{$col}");
			$model::isAQB($qb,$model_alias);

			$last_alias = $model_alias;
			$last_model = $model;
			$last_col = $col;
		}

    $manifest = $this->isAManifest();
    if(count($manifest)>0){ //we need to use the parent class id
      //get our backreference as a set of models we can hash through
      $backref = array_map(function($i){return $i[0];},array_values($last_model::hasAManifest()));
      $m = get_called_class();
      foreach($manifest as $col=>$next_m){ // join in parent classes
				$owner_m = $m::aliasForOwner($col);
				$col_val = $this[$col]==""?"0":$this[$col]; // use 0 for empty values
        $qb->join($m::$storage_table." ".$owner_m,$owner_m.".".$col."=".$col_val);
        if(in_array($m,$backref)) // step in with our actual filter value
          $qb->filter(array("{$last_alias}.{$key_col}"=>$key_val));
        $key_val = $this[$col];
        $m=$next_m;
      }
    }else{ //use our own ID
			$qb->filter(array("{$last_alias}.{$key_col}"=>$key_val));
		}
		return $qb;
	}

	/**
		@return returns a set of all ids linked at each level of the given chain
	*/
	public function closure(Array $chain, Array &$defaults=array()){
		$closure = array();
		$last_model = get_called_class();
		$val = $this[static::$primary_key_column];
		$closure[get_called_class()] = $last_ids = array($val=>$val);
		foreach($chain as $c){
			$link = explode(".",$c);
			$model = $link[0];
			// get the column to back-link
			$col = count($link)>1?$link[1]:$model::columnForModel($last_model);
			// get the current primary key
			$key = $model::$primary_key_column; //id
			$arr = array();
			foreach($last_ids as $id=>$v){
				$defaults[$model] = $id;
				$filter = array($col=>$id);
				$matches = $model::select($this->db->filter($filter));
				$out = array_reduce($matches,function($out,$i) use ($key,$id){$out[$i[$key]]=$id; return $out;},array());
				$arr = $out+$arr;
			}
			$closure[$model] = $last_ids = $arr;
			$last_model = $model;
		}
		return $closure;
	}

	/**
		@note this method will delete all other records linked to the model (stale entries)
		@param chain the chain to follow for upserting
		@param vals the values to be upserted in the target table (converted to array, if necessary)
		@param builder_f an optional user function to transform the upsert parameters (default behavior is to match to the primary key column)
		@return returns the entries from the final link (should match getMany)
	*/
	public function setMany($chainOrName,$vals,$builder_f=null){
		if($vals===null)
			return; // skip this--it is probably recursion-limited, otherwise it should be an empty row
		$chain = $chainOrName;
	    if(!is_array($chainOrName)){
		    $manifest = $this->hasManyManifest();
		    $chain = $manifest[$chainOrName];
		}
		if(is_integer($chain[count($chain)-1])) // take the recursion limit off
			array_pop($chain);

	    //prepare the parameters for filter/upsert in the target table (builder_f)
	    $link = explode(".",$chain[count($chain)-1]);
	    $target_class = $link[0];
		$key = $target_class::$primary_key_column;
		if(!isset($builder_f)){
			$builder_f = function($out,$i) use ($key){
				$out[] = is_array($i)?$i:array($key=>$i);
				return $out;
			};
		}
		$params = array_reduce($vals,$builder_f,array());

		$defaults = array();
		$stale_sets = $this->closure($chain,$defaults);

		// do the chain of upserts
		$delete_stale = count($chain)==1?true:false; //don't delete from the destination table, unless it's the only stop
		$inserted = array();
		$first_link = true;
		array_unshift($chain,get_called_class());
		while(count($chain)>1){
			$link1 = explode(".",array_pop($chain));
		    $model = $link1[0];
		    $link2 = explode(".",$chain[count($chain)-1]);
		    $next_model = $link2[0];
		    $col = count($link1)>1?$link1[1]:$model::columnForModel($next_model);
		    $next_col = count($link2)>1?$link2[1]:$next_model::columnForModel($model);

			$stale = $stale_sets[$model];
			$default_v = (isset($defaults[$model]))?$defaults[$model]:0;
			$last_params = array();
			$i = 0;
			foreach($params as $p){
				$vs=array_values($p);
                $v = (count($vs)>0)?$vs[0]:null;
				// if $nextmodel hasMany $model, hook up col=>nextmodel.key
				if($col!=$model::$primary_key_column){
					if(isset($stale[$v]))
						$p[$col] = $stale[$v];
					else //default (for new entries) is to link to the first entry from the previous table
						$p[$col] = $default_v;
				}
				$obj = $model::upsert($this->db->filter($model::searchFilter($p,$this->db)),$p);
				if($first_link) //we're doing the last table
					$inserted[] = $obj;
				unset($stale[$obj[$model::$primary_key_column]]);
				$last_params[]=($next_col!=$next_model::$primary_key_column)?array($next_col=>$obj[$col]):array($next_col=>$p[$col]);
				$i++;
			}
			if(count($stale)>0){
				if($delete_stale)
					$model::deleteRows($this->db->filter(array($model::$primary_key_column=>array("IN",array_keys($stale)))));
				else if ($first_link) // we can't delete from the destination table, but we should unset the association
					if($col!=$model::$primary_key_column) // need to clear our back-link
						$model::updateRows($this->db->filter(array($model::$primary_key_column=>array("IN",array_keys($stale)))),array($col=>null));
			}

			$delete_stale = true;
			$first_link = false;
			$params = $last_params;
		}

		return $inserted;
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

	public function storageProperties(DTStore $db,array $defaults=array(),$purpose=null){
		$storage_params = array();
		$cols = $db->columnsForTable(static::$storage_table);
		if(count($cols)==0)
			DTLog::error("Found 0 columns for table (".static::$storage_table.")");
		foreach($cols as $k){
			if($purpose!="insert"||$k!=static::$primary_key_column){ //don't try to insert the id, assume it's autoincrementing
				if($this->isDirty($k)) //we don't want to unset columns we don't control (subsetted models)
					$storage_params[$k] = $this[$k];
			}
		}
		return array_merge($defaults,$storage_params);
	}

	/**
		cleans properties in preparation for storage
	*/
	public function clean(DTStore $db=null){
		$db = isset($db)?$db:$this->db;
		$p = new DTParams($this->storageProperties($db,array(),"reinsertion"));
		$clean = $p->allParams();
		foreach($clean as $k=>$v)
			$this[$k] = $v;
		$this->_unsanitary = false;
	}

	/** attempts to set each property as defined in +params+ (but never merges id property)
		@param the parameters to merge in
		@param changes - on return, this is a description of the changes to storage
		@return returns the number of properties updated to new values
	*/
	public function merge(array $params, &$changes=null){
		$this->input = $params;
		if($changes==null)
			$changes = array("old"=>array(),"new"=>array());
		$cols = $this->db->columnsForTable(static::$storage_table);
		$updated = 0;
		foreach($params as $k=>$v){
			$old_val = $this[$k];
			// don't set the primary key, no matter what anyone says
			if($k!=static::$primary_key_column){
				//don't record changes that don't affect storage
				if(in_array($k, $cols) && $old_val!=$v){
					$changes["old"][$k] = $old_val;
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
	public function insert(DTStore $db=null,DTQueryBuilder $qb=null){
		$db = (isset($db)?$db:$this->db);
		$this->setStore($db);
		$qb = (isset($qb)?$qb:new DTQueryBuilder($db)); //allow the query builder to be passed, in case it's a subclass
		$properties = $this->storageProperties($db,array(),"insert");
		$new_id = $qb->from(static::$storage_table)->insert($properties);
		$this[static::$primary_key_column] = $new_id;
		return $new_id;
	}

	/**
		convenience method for basic updates based on storageProperties()
		@note uses the object's id property for where-clause, unless query-builder is passed
	*/
	public function update(DTStore $db=null,$qb=null){
		if($this->_unsanitary){
			DTLog::warn(DTLog::colorize(get_called_class()." updated without calling clean()"),"warn");
			DTLog::debug($this);
			DTLog::debug(DTLog::lastBacktrace());
		}
		$db = (isset($db)?$db:$this->db);
		$qb = isset($qb)?$qb:$db->filter(array(static::$primary_key_column=>$this[static::$primary_key_column]));
		$properties = $this->storageProperties($db,array(),"update");
		return $qb->from(static::$storage_table)->update($properties);
	}

	/**
		delete the object in storage
		@param qb - this determines what is matched for delete, defaults to primary-key column
	*/
	public function delete(DTStore $db=null,$qb=null){
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
			$obj->upsertAncestors($params);
			$obj->update($qb->db); //it's essential that this use *only* the +primary_key_column+
		}catch(Exception $e){
			if($e->getCode()==1){ //the record doesn't exist, insert it instead
				$obj = new static(array("db"=>$qb->db)); //the store needs to be available in the constructor
				$obj->clean();
				$obj->merge($defaults); //use the accessor for defaults
				$obj->insert($qb->db);
				$obj->merge($params,$changes); //this has to happen after insertion to have the id available for setMany
				$obj->upsertAncestors($params); //must be after merge
				$obj->update($qb->db);
			}else
				throw $e;
		}
		return static::byID($qb->db,$obj[static::$primary_key_column]);
	}

	protected function upsertAncestors(array $params){
		$manifest = array_reverse(static::isAManifest());
		foreach($manifest as $col=>$m){
			$link = explode(".",$m);
			$dst_model = $link[0];
			$dst_col = count($link)>1?$link[1]:$dst_model::$primary_key_column;
			$old_id = isset($params[$col])?$params[$col]:0;
			$parent=$dst_model::upsert($this->db->filter(array("{$dst_model}.{$dst_col}"=>$this[$col])),$params);
			$this[$col] = $parent[$dst_col];
		}
	}

	/** called during instantiation from storage--override to modify QB */
	public static function selectQB($qb){
		$qb->from(static::$storage_table." ".get_called_class());
    //$qb = static::isAQB($qb); //disabled because this needs to happen before other joins
    $qb = static::isAQB($qb);
		$manifest = static::isAManifest();
		if(count($manifest)>0)
			$qb->addColumns(array(get_called_class().".*")); //make sure we get our own ID, not a subclass
		return $qb;
	}

	public static function isAQB(DTQueryBuilder $qb,$alias=null){
		if(!isset($alias))
			$alias = get_called_class();
		$manifest = static::isAManifest();
		$i = 0;
		foreach($manifest as $col=>$m){
			$link = explode(".",$m);
			$dst_model = $link[0];
			$dst_col = count($link)>1?$link[1]:$dst_model::$primary_key_column;
			$dst_alias = "{$dst_model}_{$i}";
			$dst = "{$dst_model::$storage_table} {$dst_alias}";
			$qb->join($dst,$alias.".{$col}={$dst_alias}.{$dst_col}");
			$qb = $dst_model::isAQB($qb,$dst_alias); // also join in the parent's selectQB()
			$qb->addColumns(array($dst_alias.".*")); // makes parent attributes available
			$i++;
      break; //don't step up to the parents
		}
		return $qb;
	}

	/** traverses the is_a hierarchy for the attribute owner */
	public static function aliasForOwner($col){
		if($col==static::$primary_key_column) // drop out here, if we're talking about the primary key
			return get_called_class();
		$ref = new ReflectionClass(get_called_class());
		$publics = $ref->getProperties();
		//$publics = $db->columnsForTable(static::$storage_table); //we could get these from the table, but it would depend on the $db
		foreach($publics as $p){
			if($p->name==$col)
				return static::aliasForParent($p->class);
		}
		return get_called_class();
	}

	public static function select(DTQueryBuilder $qb,$cols=null){
		static::selectQB($qb);
	    // older versions of postgresql cannot handle a general * here (associated tables can't be grouped in)
		//$cols = isset($cols)?$cols:"*, ".get_called_class().".*";
		$cols = isset($cols)?$cols:get_called_class().".*";
		return $qb->selectAs(get_called_class(),$cols);
	}

	public static function selectKV(DTQueryBuilder $qb,$cols){
		static::selectQB($qb);
		return $qb->selectKV($cols);
	}

	public static function count(DTQueryBuilder $qb){
		return $qb->from(static::$storage_table." ".get_called_class())->count(get_called_class().".*");
	}

	public static function sum(DTQueryBuilder $qb,$col){
		return $qb->from(static::$storage_table." ".get_called_class())->sum($col);
	}

	public static function updateRows(DTQueryBuilder $qb,$params){
		return $qb->from(static::$storage_table)->update($params);
	}

	public static function deleteRows(DTQueryBuilder $qb){
		return $qb->from(static::$storage_table)->delete();
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
		if(empty($salt)) //bypass encoding
			return $str;
		if($str=="")
			return null;
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
		if(empty($salt)) //bypass decoding
			return $str;
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
		if($ts=="")
			return "";
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

	public function timeAM($ts){
		return isset($ts)?date("h:iA",strtotime($ts)):"";
	}

///@}

	public function __toString(){
		return json_encode($this->publicProperties());
	}

	public function getA($class,$column){
		try{
			return new $class($this->db->filter(array($class::$primary_key_column=>$this[$column])));
		}catch(Exception $e){}
		return null;
	}

	/** this does an immediate upsert
		@param name - the name of the manifest entry
		@param params - the attributes to match/upsert. Defaults to full match on post-accessor storage properties
		@return returns the primary key of the new object */
	public function setA($name,$params=null){
		if($params == "NULL") // we have a cleaned NULL value here
			return null;
		$manifest = static::hasAManifest();
		$class = $manifest[$name][0];
		$col = $manifest[$name][1];
		if(empty($params)) //default to exact match on preprocessed params
			$params = $class::processForStorage($this->input,$this->db);
		$obj = $class::upsert($this->db->filter($class::searchFilter($params,$this->db)),$params);
		$this[$col] = $obj[$class::$primary_key_column];
		return $obj;
	}

	/**
		runs a set of parameters through model-building logic, handy for upserts that require transformation
		@return returns the model-processed properties ready for storage
	*/
	public static function processForStorage($params,$db){
		$obj = new static(array("db"=>$db));
		$obj->merge($params);
		$properties = $obj->storageProperties($db);
		unset($properties[static::$primary_key_column]);
		return $properties;
	}

	protected static function hasManyManifest(){
		static $manifests = array();
		if(!isset($manifests[get_called_class()])){
			$manifests[get_called_class()] = static::$has_many_manifest;
			if($parent=get_parent_class(get_called_class()))
				$manifests[get_called_class()] = array_merge($parent::hasManyManifest(),$manifests[get_called_class()]);

		}
		return $manifests[get_called_class()];
	}

	protected static function modelFor($name){
		$manifest = static::hasManyManifest();
		return $manifest[$name][count($manifest[$name])-1];
	}

	protected static function hasAManifest(){
		static $manifests = array();
		if(!isset($manifests[get_called_class()])){
			$manifests[get_called_class()] = static::$has_a_manifest;
			if($parent=get_parent_class(get_called_class()))
				$manifests[get_called_class()] = array_merge($parent::hasAManifest(),$manifests[get_called_class()]);
		}
		return $manifests[get_called_class()];
	}

	protected static function isAManifest(){
		static $manifests = array();
		if(!isset($manifests[get_called_class()])){
			$manifests[get_called_class()] = static::$is_a_manifest;
			if($parent=get_parent_class(get_called_class()))
				$manifests[get_called_class()] = array_merge($manifests[get_called_class()],$parent::isAManifest());
		}
		return $manifests[get_called_class()];
	}

	public static function aliasForParent($model){
		if($model==get_called_class())
			return $model."_0";
		$manifest = static::isAManifest();
		$i = 0;
		foreach($manifest as $k=>$class){
			if($class==$model)
				return $model."_".$i;
			$i++;
		}
		if( $parent = get_parent_class(get_called_class()) )
			return $parent::aliasForParent($model);
		return null;
	}

  /** gets the names of all of the ancestors in ascending order (including self) */
  public static function ancestors(){
    //return array(get_called_class()); // TEMPORARILY DISABLED
    static $ancestors = null;
    if(!isset($ancestors)){
      $ancestors = array(get_called_class());
      if(($parent = get_parent_class(get_called_class())) != "DTModel")
        $ancestors = array_merge($ancestors,$parent::ancestors());
    }
    return $ancestors;
  }

	protected static function columnForModel($model){
		$manifest = static::hasAManifest();
		do{ //crawl up the ancencestors
			foreach($manifest as $m){
				$parts = explode(".",$m[0]);
				if(in_array($model,$parts[0]::ancestors())){
					return $m[1];
        }
			}
		}while(($model=get_parent_class($model))!=false);
		return static::$primary_key_column; //we've got the relationship backward
	}

	public function primaryKey(){
		return $this[static::$primary_key_column];
	}

	public static function primaryKeyColumn(){
		return static::$primary_key_column;
	}

	/**
	*	@return Array a SELECT-safe set of parameters, by removing all non-storage params
	*/
	public static function searchFilter($params,$db){
		$model = get_called_class();
		$storage_cols = array_flip($db->columnsForTable(static::$storage_table));
		$cols = array_intersect_key($params,$storage_cols);
		$out=array();
		foreach($cols as $k=>$v) // qualify the column names with the model
			$out["{$model}.{$k}"] = $v;
		// we don't always join on the parent (e.g. creating), so we can't do this...
		//$parent = get_parent_class(get_called_class());
		//if($parent)
		//	$out = array_merge($out,$parent::searchFilter($params,$db));
		return $out;
	}
}
